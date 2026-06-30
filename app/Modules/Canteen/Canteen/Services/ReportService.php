<?php

namespace App\Modules\Canteen\Services;

use App\Modules\Canteen\Models\Category;
use App\Modules\Canteen\Models\Product;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Models\SaleItem;
use App\Modules\Canteen\Support\SaleStatus;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function __construct(
        protected InventoryLedgerService $inventory,
        protected CanteenSettingsService $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function salesReport(Carbon $from, Carbon $to, array $filters = []): array
    {
        $query = Sale::query()
            ->with('cashier:id,name')
            ->whereBetween('sold_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderByDesc('sold_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['cashier_user_id'])) {
            $query->where('cashier_user_id', $filters['cashier_user_id']);
        }

        $sales = $query->get();

        $totalRevenue = (float) $sales->sum('total');
        $totalSales = $sales->count();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'summary' => [
                'total_sales' => $totalSales,
                'total_revenue' => round($totalRevenue, 2),
                'average_sale_value' => $totalSales > 0 ? round($totalRevenue / $totalSales, 2) : 0,
            ],
            'rows' => $sales->map(fn (Sale $sale) => [
                'date' => $sale->sold_at?->toDateString(),
                'sale_number' => $sale->sale_number,
                'student' => $sale->student_name,
                'cashier' => $sale->cashier?->name ?? '—',
                'payment_method' => $sale->payment_method,
                'total' => (float) $sale->total,
                'status' => $sale->status,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function productSalesReport(Carbon $from, Carbon $to, array $filters = []): array
    {
        $query = SaleItem::query()
            ->join('canteen_sales', 'canteen_sale_items.sale_id', '=', 'canteen_sales.id')
            ->join('canteen_products', 'canteen_sale_items.product_id', '=', 'canteen_products.id')
            ->where('canteen_sales.status', SaleStatus::COMPLETED)
            ->whereBetween('canteen_sales.sold_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        if (! empty($filters['category_id'])) {
            $query->where('canteen_products.category_id', $filters['category_id']);
        }

        $rows = $query
            ->select(
                'canteen_sale_items.product_name as product',
                'canteen_sale_items.product_sku as sku',
                DB::raw('SUM(canteen_sale_items.quantity) as quantity_sold'),
                DB::raw('SUM(canteen_sale_items.line_total) as revenue'),
                DB::raw('SUM(canteen_sale_items.quantity * COALESCE(canteen_products.cost_price, 0)) as cost'),
            )
            ->groupBy('canteen_sale_items.product_name', 'canteen_sale_items.product_sku')
            ->orderByDesc('revenue')
            ->get()
            ->map(function ($row) {
                $revenue = (float) $row->revenue;
                $cost = (float) $row->cost;

                return [
                    'product' => $row->product,
                    'sku' => $row->sku,
                    'quantity_sold' => (float) $row->quantity_sold,
                    'revenue' => round($revenue, 2),
                    'cost' => round($cost, 2),
                    'profit' => round($revenue - $cost, 2),
                ];
            });

        $sorted = $rows->sortByDesc('quantity_sold')->values();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'summary' => [
                'top_selling' => $sorted->take(5)->values()->all(),
                'lowest_selling' => $sorted->sortBy('quantity_sold')->take(5)->values()->all(),
            ],
            'rows' => $rows->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function inventoryReport(array $filters = []): array
    {
        $threshold = $this->settings->lowStockThreshold();

        $query = Product::query()
            ->with('category:id,name')
            ->when(! empty($filters['category_id']), fn ($q) => $q->where('category_id', $filters['category_id']))
            ->orderBy('name');

        $products = $query->get();
        $stock = $this->inventory->stockMap($products->pluck('id')->all());

        $rows = $products->map(function (Product $product) use ($stock, $threshold) {
            $onHand = (float) ($stock[$product->id] ?? 0);
            $isOut = $onHand <= 0;
            $isLow = $onHand > 0 && $onHand <= $threshold;

            return [
                'product' => $product->name,
                'sku' => $product->sku,
                'category' => $product->category?->name,
                'current_stock' => $onHand,
                'minimum_stock' => $threshold,
                'is_low_stock' => $isLow,
                'is_out_of_stock' => $isOut,
            ];
        });

        if (! empty($filters['stock_status'])) {
            $rows = $rows->filter(function (array $row) use ($filters) {
                return match ($filters['stock_status']) {
                    'low' => $row['is_low_stock'],
                    'out' => $row['is_out_of_stock'],
                    'ok' => ! $row['is_low_stock'] && ! $row['is_out_of_stock'],
                    default => true,
                };
            });
        }

        return [
            'summary' => [
                'total_products' => $rows->count(),
                'low_stock_count' => $rows->where('is_low_stock', true)->count(),
                'out_of_stock_count' => $rows->where('is_out_of_stock', true)->count(),
            ],
            'rows' => $rows->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function studentSpendingReport(Carbon $from, Carbon $to, array $filters = []): array
    {
        $query = Sale::query()
            ->where('status', SaleStatus::COMPLETED)
            ->whereBetween('sold_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        if (! empty($filters['student_id_ref'])) {
            $query->where('student_id_ref', 'like', '%'.$filters['student_id_ref'].'%');
        }

        if (! empty($filters['grade'])) {
            $query->where('grade', $filters['grade']);
        }

        $rows = $query
            ->select(
                'student_id_ref',
                'student_name',
                'grade',
                DB::raw('COUNT(*) as total_purchases'),
                DB::raw('SUM(total) as total_spent'),
            )
            ->groupBy('student_id_ref', 'student_name', 'grade')
            ->orderByDesc('total_spent')
            ->get()
            ->map(function ($row) {
                $purchases = (int) $row->total_purchases;
                $spent = (float) $row->total_spent;

                return [
                    'student_id_ref' => $row->student_id_ref,
                    'student' => $row->student_name,
                    'grade' => $row->grade,
                    'total_purchases' => $purchases,
                    'total_spent' => round($spent, 2),
                    'average_spend' => $purchases > 0 ? round($spent / $purchases, 2) : 0,
                ];
            });

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'rows' => $rows->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function categorySalesReport(Carbon $from, Carbon $to, array $filters = []): array
    {
        $rows = SaleItem::query()
            ->join('canteen_products', 'canteen_sale_items.product_id', '=', 'canteen_products.id')
            ->join('canteen_categories', 'canteen_products.category_id', '=', 'canteen_categories.id')
            ->join('canteen_sales', 'canteen_sale_items.sale_id', '=', 'canteen_sales.id')
            ->where('canteen_sales.status', SaleStatus::COMPLETED)
            ->whereBetween('canteen_sales.sold_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->select(
                'canteen_categories.name as category',
                DB::raw('SUM(canteen_sale_items.quantity) as quantity_sold'),
                DB::raw('SUM(canteen_sale_items.line_total) as revenue'),
            )
            ->groupBy('canteen_categories.name')
            ->orderByDesc('revenue')
            ->get();

        $totalRevenue = (float) $rows->sum('revenue');

        $mapped = $rows->map(function ($row) use ($totalRevenue) {
            $revenue = (float) $row->revenue;

            return [
                'category' => $row->category,
                'quantity_sold' => (float) $row->quantity_sold,
                'revenue' => round($revenue, 2),
                'percentage_of_total' => $totalRevenue > 0
                    ? round(($revenue / $totalRevenue) * 100, 2)
                    : 0,
            ];
        });

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'summary' => [
                'total_revenue' => round($totalRevenue, 2),
            ],
            'rows' => $mapped->values()->all(),
        ];
    }

    public function filterOptions(): array
    {
        $cashierIds = Sale::query()->distinct()->pluck('cashier_user_id')->filter();

        return [
            'cashiers' => User::query()
                ->whereIn('id', $cashierIds)
                ->orderBy('name')
                ->get(['id', 'name']),
            'categories' => Category::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'grades' => Sale::query()
                ->whereNotNull('grade')
                ->distinct()
                ->orderBy('grade')
                ->pluck('grade'),
            'statuses' => [
                SaleStatus::PENDING_PAYMENT,
                SaleStatus::COMPLETED,
                SaleStatus::VOIDED,
                SaleStatus::FAILED,
            ],
            'stock_statuses' => [
                ['value' => 'low', 'label' => 'Low Stock'],
                ['value' => 'out', 'label' => 'Out of Stock'],
                ['value' => 'ok', 'label' => 'In Stock'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function resolve(string $type, Carbon $from, Carbon $to, array $filters = []): array
    {
        return match ($type) {
            'sales' => $this->salesReport($from, $to, $filters),
            'products' => $this->productSalesReport($from, $to, $filters),
            'inventory' => $this->inventoryReport($filters),
            'students' => $this->studentSpendingReport($from, $to, $filters),
            'categories' => $this->categorySalesReport($from, $to, $filters),
            default => abort(404),
        };
    }
}
