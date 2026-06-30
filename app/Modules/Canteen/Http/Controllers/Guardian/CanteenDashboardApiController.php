<?php

namespace App\Modules\Canteen\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Services\CanteenParentDashboardService;
use App\Modules\Canteen\Support\CanteenPermission;
use Illuminate\Http\Request;

class CanteenDashboardApiController extends Controller
{
    public function __construct(protected CanteenParentDashboardService $dashboard) {}

    public function summary(Request $request)
    {
        $this->authorizeTransactionsView();

        return response()->json(
            $this->dashboard->summaryForGuardian($request->user())
        );
    }

    public function purchases(Request $request, User $student)
    {
        $this->authorizeTransactionsView();

        $paginator = $this->dashboard->purchasesForStudent(
            $student,
            (int) $request->query('per_page', 15),
        );

        return response()->json([
            'student_id' => $student->id,
            'data' => $paginator->getCollection()->map(fn (Sale $sale) => $this->formatSale($sale))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function showPurchase(User $student, Sale $sale)
    {
        $this->authorizeTransactionsView();

        $detail = $this->dashboard->purchaseDetail($student, $sale);
        $sale = $detail['sale'];

        return response()->json([
            'student_id' => $student->id,
            'sale' => $this->formatSale($sale, includeItems: true),
            'wallet_settlement' => $detail['wallet_settlement'],
        ]);
    }

    public function spending(Request $request, User $student)
    {
        $this->authorizeTransactionsView();

        $from = $request->query('from');
        $to = $request->query('to');

        return response()->json(
            $this->dashboard->spendingSummary(
                $student,
                $from ? \Illuminate\Support\Carbon::parse($from) : null,
                $to ? \Illuminate\Support\Carbon::parse($to) : null,
            )
        );
    }

    protected function authorizeTransactionsView(): void
    {
        abort_unless(
            CanteenPermission::allows(auth()->user(), 'canteen.parent.transactions.view'),
            403,
            'You are not allowed to view canteen transactions.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatSale(Sale $sale, bool $includeItems = false): array
    {
        $payload = [
            'id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'status' => $sale->status,
            'subtotal' => (string) $sale->subtotal,
            'discount' => (string) $sale->discount,
            'total' => (string) $sale->total,
            'sold_at' => $sale->sold_at?->toIso8601String(),
            'completed_at' => $sale->completed_at?->toIso8601String(),
        ];

        if ($includeItems) {
            $payload['items'] = $sale->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'product_sku' => $item->product_sku,
                'unit_price' => (string) $item->unit_price,
                'quantity' => (string) $item->quantity,
                'line_total' => (string) $item->line_total,
            ])->values()->all();
        }

        return $payload;
    }
}
