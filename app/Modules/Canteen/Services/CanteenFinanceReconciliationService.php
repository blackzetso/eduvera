<?php

namespace App\Modules\Canteen\Services;

use App\Modules\Canteen\Models\CanteenFinanceEntry;
use App\Modules\Canteen\Models\InventoryTransaction;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Support\SaleStatus;
use Illuminate\Support\Carbon;

class CanteenFinanceReconciliationService
{
    /**
     * @return array{from: string, to: string, rows: list<array<string, mixed>>, summary: array<string, int>}
     */
    public function reconcile(Carbon $from, Carbon $to): array
    {
        $sales = Sale::query()
            ->with(['walletReadyTransaction', 'items'])
            ->whereIn('status', [SaleStatus::COMPLETED, SaleStatus::VOIDED, SaleStatus::FAILED])
            ->whereBetween('sold_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('sold_at')
            ->get();

        $rows = [];
        $summary = [
            'matched' => 0,
            'wallet_missing' => 0,
            'inventory_missing' => 0,
            'amount_mismatch' => 0,
            'finance_missing' => 0,
        ];

        foreach ($sales as $sale) {
            $row = $this->reconcileSale($sale);
            $rows[] = $row;
            $summary[$row['status']]++;
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reconcileSale(Sale $sale): array
    {
        $walletTx = $sale->walletReadyTransaction;
        $financeEntries = CanteenFinanceEntry::query()->where('sale_id', $sale->id)->get();
        $inventoryCount = InventoryTransaction::query()
            ->where('reference_type', 'sale')
            ->where('reference_id', $sale->id)
            ->count();

        $status = 'matched';

        if ($sale->status === SaleStatus::COMPLETED && $financeEntries->isEmpty()) {
            $status = 'finance_missing';
        }

        if ($walletTx && bccomp((string) $walletTx->amount, (string) $sale->total, 2) !== 0) {
            $status = 'amount_mismatch';
        }

        if ($sale->status === SaleStatus::COMPLETED && $inventoryCount === 0) {
            $status = $status === 'matched' ? 'inventory_missing' : $status;
        }

        if ($sale->status === SaleStatus::COMPLETED
            && config('canteen.integration.wallet_adapter') === 'user_wallet'
            && ! $walletTx?->external_wallet_tx_id) {
            $status = 'wallet_missing';
        }

        return [
            'sale_id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'sale_status' => $sale->status,
            'sale_total' => (string) $sale->total,
            'wallet_settlement_id' => $walletTx?->id,
            'wallet_tx_id' => $walletTx?->external_wallet_tx_id,
            'wallet_amount' => $walletTx ? (string) $walletTx->amount : null,
            'finance_entry_count' => $financeEntries->count(),
            'inventory_transaction_count' => $inventoryCount,
            'status' => $status,
        ];
    }
}
