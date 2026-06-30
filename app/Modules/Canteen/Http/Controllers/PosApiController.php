<?php

namespace App\Modules\Canteen\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserWallet;
use App\Modules\Canteen\Http\Requests\PosCheckoutRequest;
use App\Modules\Canteen\Http\Resources\ProductResource;
use App\Modules\Canteen\Http\Resources\SaleResource;
use App\Modules\Canteen\Integration\Contracts\StudentIdentityPort;
use App\Modules\Canteen\Models\Product;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Models\StudentProfile;
use App\Modules\Canteen\Services\CanteenStudentEligibilityService;
use App\Modules\Canteen\Services\DailyLimitService;
use App\Modules\Canteen\Services\InventoryLedgerService;
use App\Modules\Canteen\Services\PosCheckoutService;
use App\Modules\Canteen\Services\ProductService;
use App\Modules\Canteen\Services\RestrictionEngineService;
use App\Modules\Canteen\Services\StudentBlockService;
use App\Services\StudentEnrollmentService;
use App\Support\Student\StudentStatus;
use Illuminate\Http\Request;

class PosApiController extends Controller
{
    public function __construct(
        protected ProductService $products,
        protected InventoryLedgerService $inventory,
        protected StudentIdentityPort $students,
        protected DailyLimitService $dailyLimit,
        protected RestrictionEngineService $restrictions,
        protected StudentBlockService $studentBlocks,
        protected PosCheckoutService $checkout,
        protected CanteenStudentEligibilityService $eligibility,
        protected StudentEnrollmentService $enrollments,
    ) {}

    public function products(Request $request)
    {
        $catalog = $this->products->listActiveCatalog($request->only(['category_id', 'search']));
        $stock = $this->inventory->stockMap($catalog->pluck('id')->all());
        $catalog->transform(fn ($p) => tap($p, fn ($x) => $x->setAttribute('on_hand', $stock[$p->id] ?? '0')));

        return ProductResource::collection($catalog);
    }

    public function barcode(string $code)
    {
        $product = $this->products->findByBarcode($code);
        if (! $product) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        $product->setAttribute('on_hand', $this->inventory->onHand($product->id));

        return new ProductResource($product);
    }

    public function searchStudents(Request $request)
    {
        $q = (string) $request->query('q', '');

        return response()->json(array_map(
            fn ($snapshot) => $snapshot->toArray(),
            $this->students->search($q, 20),
        ));
    }

    public function eligibility(string $ref)
    {
        $studentUser = $this->resolveStudentUser($ref);
        $profile = StudentProfile::query()->where('student_id_ref', $ref)->first();
        $snapshot = $this->students->findByRef($ref);
        $blockReason = $studentUser ? $this->eligibility->purchaseBlockReason($studentUser) : null;
        $enrollment = $studentUser ? $this->enrollments->currentEnrollment($studentUser) : null;

        if ($snapshot === null && $blockReason === null) {
            $blockReason = 'Student not found or not eligible for canteen purchases.';
        }

        $walletBalance = 0.0;

        if ($studentUser) {
            $wallet = UserWallet::query()->where('user_id', $studentUser->id)->first();
            $walletBalance = (float) ($wallet?->balance ?? 0);
        }

        return response()->json([
            'student' => $snapshot?->toArray(),
            'daily_limit' => [
                'limit' => $this->dailyLimit->getLimit($profile),
                'spent' => $this->dailyLimit->spentToday($ref),
                'remaining' => $this->dailyLimit->remaining($profile),
            ],
            'parent_blocks' => $this->studentBlocks->summaryForStudent($ref),
            'purchasable' => $snapshot !== null && $blockReason === null,
            'reason' => $blockReason,
            'wallet_balance' => $walletBalance,
            'wallet_adapter' => config('canteen.integration.wallet_adapter', 'pending'),
            'student_status' => $studentUser?->student_status ?? StudentStatus::ACTIVE,
            'enrollment_status' => $enrollment?->status,
        ]);
    }

    public function checkProductBlock(Request $request, string $ref)
    {
        $request->validate([
            'product_id' => ['required', 'uuid', 'exists:canteen_products,id'],
        ]);

        $product = Product::query()
            ->with('category:id,name,name_ar,slug')
            ->findOrFail($request->input('product_id'));

        $violation = $this->studentBlocks->checkProduct($ref, $product);

        return response()->json([
            'blocked' => $violation !== null,
            'violation' => $violation,
        ]);
    }

    public function validateCart(Request $request)
    {
        $request->validate([
            'student_id_ref' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        return response()->json(
            $this->checkout->validateCart($request->input('student_id_ref'), $request->input('items'))
        );
    }

    public function storeSale(PosCheckoutRequest $request)
    {
        $sale = $this->checkout->checkout($request->validated(), $request->user()->id);

        return (new SaleResource($sale))->response()->setStatusCode(201);
    }

    public function overrideLimit(Request $request, Sale $sale)
    {
        return response()->json(['message' => 'Use checkout with limit_override flag.'], 400);
    }

    public function today(Request $request)
    {
        $sales = Sale::query()
            ->with('items')
            ->where('cashier_user_id', $request->user()->id)
            ->where('status', 'completed')
            ->whereDate('sold_at', today())
            ->orderByDesc('sold_at')
            ->get();

        return SaleResource::collection($sales);
    }

    protected function resolveStudentUser(string $ref): ?User
    {
        $ref = trim($ref);

        if ($ref === '') {
            return null;
        }

        if (ctype_digit($ref)) {
            return User::query()->students()->find((int) $ref);
        }

        return User::query()->students()->where('student_code', $ref)->first();
    }
}
