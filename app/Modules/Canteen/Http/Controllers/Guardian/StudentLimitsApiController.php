<?php

namespace App\Modules\Canteen\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Canteen\Http\Requests\Guardian\StoreGuardianBlockedCategoryRequest;
use App\Modules\Canteen\Http\Requests\Guardian\StoreGuardianBlockedProductRequest;
use App\Modules\Canteen\Http\Requests\Guardian\UpdateGuardianDailyLimitRequest;
use App\Modules\Canteen\Http\Requests\Guardian\UpdateHealthRestrictionsRequest;
use App\Modules\Canteen\Models\StudentBlockedCategory;
use App\Modules\Canteen\Models\StudentBlockedProduct;
use App\Modules\Canteen\Services\DailyLimitService;
use App\Modules\Canteen\Services\GuardianCanteenStudentService;
use App\Modules\Canteen\Services\StudentBlockService;
use App\Modules\Canteen\Support\CanteenPermission;
use Illuminate\Http\Request;

class StudentLimitsApiController extends Controller
{
    public function __construct(
        protected DailyLimitService $dailyLimit,
        protected StudentBlockService $studentBlocks,
        protected GuardianCanteenStudentService $students,
    ) {}

    public function showLimits(User $student)
    {
        $this->authorizeParentLimits();

        $profile = $this->students->ensureProfile($student);
        $studentRef = $this->students->studentIdRef($student);

        return response()->json([
            'student_id' => $student->id,
            'student_id_ref' => $studentRef,
            'daily_limit' => [
                'limit' => $this->dailyLimit->getLimit($profile),
                'spent_today' => $this->dailyLimit->spentToday($studentRef),
                'remaining' => $this->dailyLimit->remaining($profile),
            ],
            'health_restrictions' => $profile->health_restrictions ?? [],
            'guardian_purchase_blocked' => (bool) ($profile->metadata['guardian_purchase_blocked'] ?? false),
        ]);
    }

    public function updateDailyLimit(UpdateGuardianDailyLimitRequest $request, User $student)
    {
        $this->authorizeParentLimits();

        $profile = $this->students->ensureProfile($student);
        $profile->update([
            'daily_spending_limit' => $request->validated('daily_spending_limit'),
        ]);

        return response()->json([
            'message' => 'Daily limit updated.',
            'daily_limit' => [
                'limit' => $this->dailyLimit->getLimit($profile->fresh()),
                'spent_today' => $this->dailyLimit->spentToday($this->students->studentIdRef($student)),
                'remaining' => $this->dailyLimit->remaining($profile->fresh()),
            ],
        ]);
    }

    public function blocks(User $student)
    {
        $this->authorizeParentLimits();

        $studentRef = $this->students->studentIdRef($student);
        $blocks = $this->studentBlocks->blocksForStudents(collect([$studentRef]));

        return response()->json([
            'student_id' => $student->id,
            'blocked_products' => $blocks['products'][$studentRef] ?? [],
            'blocked_categories' => $blocks['categories'][$studentRef] ?? [],
        ]);
    }

    public function storeBlockedProduct(StoreGuardianBlockedProductRequest $request, User $student)
    {
        $this->authorizeParentLimits();

        $block = $this->studentBlocks->blockProduct(
            $this->students->studentIdRef($student),
            $request->validated('product_id'),
            $request->validated() + ['block_source' => StudentBlockService::SOURCE_PARENT_REQUEST],
        );

        return response()->json([
            'message' => 'Product blocked.',
            'block' => $this->studentBlocks->blocksForStudents(
                collect([$this->students->studentIdRef($student)])
            )['products'][$this->students->studentIdRef($student)] ?? [],
        ], 201);
    }

    public function destroyBlockedProduct(User $student, StudentBlockedProduct $studentBlockedProduct)
    {
        $this->authorizeParentLimits();
        $this->assertBlockBelongsToStudent($student, $studentBlockedProduct->student_id_ref);

        $this->studentBlocks->unblockProduct($studentBlockedProduct);

        return response()->json(['message' => 'Product block removed.']);
    }

    public function storeBlockedCategory(StoreGuardianBlockedCategoryRequest $request, User $student)
    {
        $this->authorizeParentLimits();

        $this->studentBlocks->blockCategory(
            $this->students->studentIdRef($student),
            $request->validated('category_id'),
            $request->validated() + ['block_source' => StudentBlockService::SOURCE_PARENT_REQUEST],
        );

        return response()->json(['message' => 'Category blocked.'], 201);
    }

    public function destroyBlockedCategory(User $student, StudentBlockedCategory $studentBlockedCategory)
    {
        $this->authorizeParentLimits();
        $this->assertBlockBelongsToStudent($student, $studentBlockedCategory->student_id_ref);

        $this->studentBlocks->unblockCategory($studentBlockedCategory);

        return response()->json(['message' => 'Category block removed.']);
    }

    public function updateHealthRestrictions(UpdateHealthRestrictionsRequest $request, User $student)
    {
        $this->authorizeParentLimits();

        $profile = $this->students->ensureProfile($student);
        $profile->update([
            'health_restrictions' => $request->validated(),
        ]);

        return response()->json([
            'message' => 'Health restrictions updated.',
            'health_restrictions' => $profile->fresh()->health_restrictions,
        ]);
    }

    public function updatePurchaseBlocked(Request $request, User $student)
    {
        $this->authorizeParentLimits();

        $data = $request->validate([
            'blocked' => ['required', 'boolean'],
        ]);

        $profile = $this->students->ensureProfile($student);
        $metadata = $profile->metadata ?? [];
        $metadata['guardian_purchase_blocked'] = $data['blocked'];

        $profile->update(['metadata' => $metadata]);

        return response()->json([
            'message' => 'Purchase block preference updated.',
            'guardian_purchase_blocked' => (bool) $data['blocked'],
        ]);
    }

    protected function authorizeParentLimits(): void
    {
        abort_unless(
            CanteenPermission::allows(auth()->user(), 'canteen.parent.limits.manage'),
            403,
            'You are not allowed to manage student limits.'
        );
    }

    protected function assertBlockBelongsToStudent(User $student, string $studentIdRef): void
    {
        abort_unless(
            $studentIdRef === $this->students->studentIdRef($student),
            404,
            'Block not found for this student.'
        );
    }
}
