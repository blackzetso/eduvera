<?php

namespace App\Modules\Canteen\Services;

use App\Modules\Canteen\Integration\DTOs\WalletDebitResult;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Models\WalletReadyTransaction;
use App\Modules\Canteen\Support\SaleStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaleSettlementService
{
    public function __construct(protected AuditService $audit) {}

    public function confirmWalletSettlement(Sale $sale, ?WalletDebitResult $debitResult = null): Sale
    {
        if ($sale->status === SaleStatus::COMPLETED) {
            return $sale->fresh()->load('walletReadyTransaction');
        }

        if ($sale->status !== SaleStatus::PENDING_PAYMENT) {
            throw new InvalidArgumentException('Only pending settlement sales can be confirmed.');
        }

        return DB::transaction(function () use ($sale, $debitResult) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            $before = $sale->toArray();

            if ($sale->status === SaleStatus::COMPLETED) {
                return $sale->fresh()->load('walletReadyTransaction');
            }

            $walletTx = WalletReadyTransaction::query()
                ->where('sale_id', $sale->id)
                ->lockForUpdate()
                ->first();

            if (! $walletTx) {
                throw new InvalidArgumentException('No wallet settlement found for sale.');
            }

            if ($walletTx->status === 'pending') {
                $walletTx->update([
                    'status' => 'posted',
                    'posted_at' => now(),
                ]);
                $walletTx->refresh();
            }

            if ($walletTx->status !== 'posted') {
                throw new InvalidArgumentException('Wallet settlement must be posted before sale completion.');
            }

            $metadata = $sale->metadata ?? [];
            $metadata['wallet_settlement'] = [
                'transaction_id' => $walletTx->id,
                'status' => $walletTx->status,
                'source_module' => $walletTx->source_module,
                'external_wallet_tx_id' => $walletTx->external_wallet_tx_id,
                'amount' => (string) $walletTx->amount,
                'currency' => $walletTx->currency,
                'posted_at' => $walletTx->posted_at?->toIso8601String(),
                'idempotency_key' => $walletTx->idempotency_key,
            ];

            $sale->update([
                'status' => SaleStatus::COMPLETED,
                'completed_at' => now(),
                'metadata' => $metadata,
            ]);

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

            if ($walletTx && in_array($walletTx->status, ['pending', 'posted'], true)) {
                $walletTx->update([
                    'status' => 'failed',
                    'failure_reason' => $reason,
                ]);
            }

            $metadata = $sale->metadata ?? [];
            $metadata['wallet_settlement_failure'] = [
                'reason' => $reason,
                'failed_at' => now()->toIso8601String(),
            ];

            $sale->update([
                'status' => SaleStatus::FAILED,
                'metadata' => $metadata,
            ]);

            $fresh = $sale->fresh()->load('walletReadyTransaction');
            $this->audit->log('sale.settlement_failed', $fresh, before: $before, after: $fresh->toArray());

            return $fresh;
        });
    }
}
