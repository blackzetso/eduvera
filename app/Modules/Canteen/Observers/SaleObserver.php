<?php

namespace App\Modules\Canteen\Observers;

use App\Modules\Canteen\Events\CanteenSaleCompleted;
use App\Modules\Canteen\Events\CanteenSaleFailed;
use App\Modules\Canteen\Events\CanteenSaleVoided;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Support\SaleStatus;
use Illuminate\Support\Facades\DB;

class SaleObserver
{
    public function updated(Sale $sale): void
    {
        if (! $sale->wasChanged('status')) {
            return;
        }

        $status = $sale->status;
        $saleId = $sale->id;

        DB::afterCommit(function () use ($saleId, $status) {
            $fresh = Sale::query()
                ->with(['items', 'walletReadyTransaction'])
                ->find($saleId);

            if (! $fresh) {
                return;
            }

            match ($status) {
                SaleStatus::COMPLETED => event(new CanteenSaleCompleted($fresh)),
                SaleStatus::FAILED => event(new CanteenSaleFailed(
                    $fresh,
                    (string) ($fresh->metadata['wallet_settlement_failure']['reason'] ?? 'Wallet settlement failed'),
                )),
                SaleStatus::VOIDED => event(new CanteenSaleVoided(
                    $fresh,
                    (string) ($fresh->void_reason ?? 'Sale voided'),
                )),
                default => null,
            };
        });
    }
}
