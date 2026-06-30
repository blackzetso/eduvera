<?php

namespace App\Modules\Canteen\Services;

use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Models\SaleItem;
use App\Modules\Canteen\Services\RestrictionsSummaryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        protected InventoryLedgerService $inventory,
        protected RestrictionsSummaryService $restrictionsSummary,
    ) {}

    public function kpis(?Carbon $date = null): array
    {
        $date = $date ?? today();

        $sales = Sale::query()
            ->where('status', 'completed')
            ->whereDate('sold_at', $date);

        $revenue = (string) (clone $sales)->sum('total');
        $transactionsCount = (clone $sales)->count();

        $topProducts = SaleItem::query()
            ->select('product_name', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(line_total) as revenue'))
            ->whereHas('sale', fn ($q) => $q->where('status', 'completed')->whereDate('sold_at', $date))
            ->groupBy('product_name')
            ->orderByDesc('qty')
            ->limit(5)
            ->get();

        $recent = Sale::query()
            ->with('items')
            ->where('status', 'completed')
            ->orderByDesc('sold_at')
            ->limit(10)
            ->get();

        $lowStock = $this->inventory->overview(['per_page' => 100])->getCollection()
            ->filter(fn ($p) => $p->is_low_stock ?? false)
            ->values();

        $outOfStock = $this->inventory->overview(['per_page' => 100])->getCollection()
            ->filter(fn ($p) => $p->is_out_of_stock ?? false)
            ->values();

        $trends = Sale::query()
            ->select(DB::raw('DATE(sold_at) as day'), DB::raw('SUM(total) as revenue'), DB::raw('COUNT(*) as count'))
            ->where('status', 'completed')
            ->where('sold_at', '>=', now()->subDays(7))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return [
            'date' => $date->toDateString(),
            'revenue' => $revenue,
            'transactions_count' => $transactionsCount,
            'top_products' => $topProducts,
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'recent_transactions' => $recent,
            'sales_trends' => $trends,
            'restrictions' => $this->restrictionsSummary->summary(),
        ];
    }
}
