<?php

namespace App\Modules\Canteen\Services;

use App\Modules\Canteen\Models\InventoryTransaction;
use App\Modules\Canteen\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryLedgerService
{
    public function __construct(
        protected AuditService $audit,
        protected CanteenSettingsService $settings,
    ) {}

    public function onHand(string $productId): string
    {
        $sum = InventoryTransaction::query()
            ->where('product_id', $productId)
            ->sum('quantity_delta');

        return (string) $sum;
    }

    public function stockMap(array $productIds = []): array
    {
        $query = InventoryTransaction::query()
            ->selectRaw('product_id, SUM(quantity_delta) as on_hand')
            ->groupBy('product_id');

        if ($productIds) {
            $query->whereIn('product_id', $productIds);
        }

        return $query->pluck('on_hand', 'product_id')->map(fn ($v) => (string) $v)->all();
    }

    public function record(
        string $productId,
        string $type,
        string $quantityDelta,
        ?string $unitCost = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $notes = null,
        ?Carbon $occurredAt = null,
    ): InventoryTransaction {
        return DB::transaction(function () use (
            $productId, $type, $quantityDelta, $unitCost, $referenceType, $referenceId, $notes, $occurredAt
        ) {
            Product::query()->findOrFail($productId);

            $tx = InventoryTransaction::query()->create([
                'product_id' => $productId,
                'type' => $type,
                'quantity_delta' => $quantityDelta,
                'unit_cost' => $unitCost,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
                'occurred_at' => $occurredAt ?? now(),
            ]);

            $this->audit->log('inventory.recorded', $tx, after: $tx->toArray());

            return $tx;
        });
    }

    public function recordSale(string $productId, string $quantity, string $saleId): InventoryTransaction
    {
        $delta = bcmul($quantity, '-1', 3);
        if (bccomp($this->onHand($productId), $quantity, 3) < 0) {
            throw new InvalidArgumentException('Insufficient stock for product.');
        }

        return $this->record($productId, 'sale', $delta, null, 'sale', $saleId);
    }

    public function recordSaleReversal(string $productId, string $quantity, string $saleId): InventoryTransaction
    {
        $existing = InventoryTransaction::query()
            ->where('product_id', $productId)
            ->where('type', 'return')
            ->where('reference_type', 'sale_void')
            ->where('reference_id', $saleId)
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->record(
            $productId,
            'return',
            $quantity,
            null,
            'sale_void',
            $saleId,
            'Sale void inventory reversal',
        );
    }

    public function ledger(string $productId, array $filters = []): LengthAwarePaginator
    {
        return InventoryTransaction::query()
            ->where('product_id', $productId)
            ->orderByDesc('occurred_at')
            ->paginate($filters['per_page'] ?? 20);
    }

    public function overview(array $filters = []): LengthAwarePaginator
    {
        $threshold = $this->settings->lowStockThreshold();

        $products = Product::query()
            ->with('category:id,name')
            ->when(! empty($filters['search']), fn ($q) => $q->where('name', 'like', '%'.$filters['search'].'%'))
            ->paginate($filters['per_page'] ?? 15);

        $stock = $this->stockMap($products->pluck('id')->all());

        $products->getCollection()->transform(function (Product $p) use ($stock, $threshold) {
            $onHand = (float) ($stock[$p->id] ?? 0);
            $p->setAttribute('on_hand', (string) $onHand);
            $p->setAttribute('is_low_stock', $onHand > 0 && $onHand <= $threshold);
            $p->setAttribute('is_out_of_stock', $onHand <= 0);

            return $p;
        });

        return $products;
    }
}
