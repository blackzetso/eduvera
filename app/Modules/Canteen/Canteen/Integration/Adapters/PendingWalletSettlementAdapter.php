<?php

namespace App\Modules\Canteen\Integration\Adapters;

use App\Modules\Canteen\Integration\Contracts\WalletSettlementPort;
use App\Modules\Canteen\Integration\DTOs\WalletDebitRequest;
use App\Modules\Canteen\Integration\DTOs\WalletDebitResult;
use App\Modules\Canteen\Models\WalletReadyTransaction;

class PendingWalletSettlementAdapter implements WalletSettlementPort
{
    public function requestDebit(WalletDebitRequest $request): WalletDebitResult
    {
        $tx = WalletReadyTransaction::query()->create([
            'sale_id' => $request->saleId,
            'student_id_ref' => $request->studentIdRef,
            'transaction_type' => 'debit',
            'amount' => $request->amount,
            'currency' => $request->currency,
            'status' => 'pending',
            'source_module' => 'canteen',
            'idempotency_key' => $request->idempotencyKey,
            'metadata' => $request->metadata,
        ]);

        return new WalletDebitResult($tx->id, $tx->status, $tx->source_module);
    }

    public function cancelDebit(string $idempotencyKey): void
    {
        WalletReadyTransaction::query()
            ->where('idempotency_key', $idempotencyKey)
            ->whereIn('status', ['pending', 'posted'])
            ->update([
                'status' => 'cancelled',
                'updated_by' => auth()->id(),
            ]);
    }
}
