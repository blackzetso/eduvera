<?php

namespace App\Modules\Canteen\Services;

use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Models\WalletReadyTransaction;
use App\Modules\Canteen\Support\SaleStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaleSettlementService
{
    public function __construct(protected AuditService $audit) {}

    public function confirmWalletSettlement(Sale $sale): Sale
    {
        if ($sale->status === SaleStatus::COMPLETED) {
            return $sale->fresh()->load('walletReadyTransaction');
        }

        if ($sale->status !== SaleStatus::PENDING_PAYMENT) {
            throw new InvalidArgumentException('Only pending settlement sales can be confirmed.');
        }

        return DB::transaction(function () use ($sale) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            $before = $sale->toArray();

            if ($sale->status === SaleStatus::COMPLETED) {
                return $sale->fresh()->load('walletReadyTransaction');
            }

            $walletTx = WalletReadyTransaction::query()
                ->where('sale_id', $sale->id)
                ->lockForUpdate()
                ->first();

            if (! $walletTx || $walletTx->status !== 'pending') {
                throw new InvalidArgumentException('No pending wallet transaction found for sale.');
            }

            $walletTx->update([
                'status' => 'posted',
                'posted_at' => now(),
            ]);

            $sale->update(['status' => SaleStatus::COMPLETED]);

            $fresh = $sale->fresh()->load('walletReadyTransaction');
            $this->audit->log('sale.settlement_confirmed', $fresh, before: $before, after: $fresh->toArray());

            return $fresh;
        });
    }

    public function failWalletSettlement(Sale $sale, string $reason): Sale
    {
        if ($sale->status === SaleStatus::FAILED) {
            return $sale->fresh()->load('walletReadyTransaction');
        }

        if ($sale->status !== SaleStatus::PENDING_PAYMENT) {
            throw new InvalidArgumentException('Only pending settlement sales can be marked failed.');
        }

        return DB::transaction(function () use ($sale, $reason) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            $before = $sale->toArray();

            if ($sale->status === SaleStatus::FAILED) {
                return $sale->fresh()->load('walletReadyTransaction');
            }

            $walletTx = WalletReadyTransaction::query()
                ->where('sale_id', $sale->id)
                ->lockForUpdate()
                ->first();

            if ($walletTx && $walletTx->status === 'pending') {
                $walletTx->update([
                    'status' => 'failed',
                    'failure_reason' => $reason,
                ]);
            }

            $sale->update(['status' => SaleStatus::FAILED]);

            $fresh = $sale->fresh()->load('walletReadyTransaction');
            $this->audit->log('sale.settlement_failed', $fresh, before: $before, after: $fresh->toArray());

            return $fresh;
        });
    }
}
