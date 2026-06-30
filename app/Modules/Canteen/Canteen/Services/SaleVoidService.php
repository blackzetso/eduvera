<?php

namespace App\Modules\Canteen\Services;

use App\Modules\Canteen\Integration\Contracts\ParentNotificationPort;
use App\Modules\Canteen\Integration\Contracts\WalletSettlementPort;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Support\SaleStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaleVoidService
{
    public function __construct(
        protected AuditService $audit,
        protected InventoryLedgerService $inventory,
        protected WalletSettlementPort $wallet,
        protected ParentNotificationPort $parentNotification,
    ) {}

    public function void(Sale $sale, string $reason, int $voidedBy): Sale
    {
        if ($sale->status === SaleStatus::VOIDED) {
            throw new InvalidArgumentException('Sale is already voided.');
        }

        if (! in_array($sale->status, SaleStatus::voidable(), true)) {
            throw new InvalidArgumentException('Sale cannot be voided in its current state.');
        }

        return DB::transaction(function () use ($sale, $reason, $voidedBy) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            $sale->load('items');

            if ($sale->status === SaleStatus::VOIDED) {
                throw new InvalidArgumentException('Sale is already voided.');
            }

            if (! in_array($sale->status, SaleStatus::voidable(), true)) {
                throw new InvalidArgumentException('Sale cannot be voided in its current state.');
            }

            $before = $sale->toArray();

            foreach ($sale->items as $item) {
                $this->inventory->recordSaleReversal(
                    $item->product_id,
                    (string) $item->quantity,
                    $sale->id,
                );
            }

            $this->wallet->cancelDebit($this->walletIdempotencyKey($sale->id));
            $this->parentNotification->reverseSaleVisibility($sale->id);

            $sale->update([
                'status' => SaleStatus::VOIDED,
                'voided_at' => now(),
                'voided_by' => $voidedBy,
                'void_reason' => $reason,
            ]);

            $fresh = $sale->fresh()->load(['items', 'walletReadyTransaction']);
            $this->audit->log('sale.voided', $fresh, before: $before, after: $fresh->toArray());

            return $fresh;
        });
    }

    protected function walletIdempotencyKey(string $saleId): string
    {
        return 'canteen-sale-'.$saleId;
    }
}
