<?php

namespace App\Modules\Canteen\Integration\Adapters;

use App\Models\User;
use App\Models\UserWallet;
use App\Models\UserWalletTransaction;
use App\Modules\Canteen\Exceptions\InsufficientWalletBalanceException;
use App\Modules\Canteen\Exceptions\WalletSettlementFailedException;
use App\Modules\Canteen\Integration\Contracts\WalletSettlementPort;
use App\Modules\Canteen\Integration\DTOs\WalletDebitRequest;
use App\Modules\Canteen\Integration\DTOs\WalletDebitResult;
use App\Modules\Canteen\Models\WalletReadyTransaction;
use Illuminate\Support\Facades\DB;

class UserWalletSettlementAdapter implements WalletSettlementPort
{
    public function requestDebit(WalletDebitRequest $request): WalletDebitResult
    {
        $existing = WalletReadyTransaction::query()
            ->where('idempotency_key', $request->idempotencyKey)
            ->first();

        if ($existing?->status === 'posted') {
            return new WalletDebitResult(
                $existing->id,
                $existing->status,
                $existing->source_module,
            );
        }

        if ($existing && $existing->status !== 'cancelled') {
            throw new WalletSettlementFailedException(
                "Wallet settlement already exists for idempotency key [{$request->idempotencyKey}] with status [{$existing->status}]."
            );
        }

        $student = $this->resolveStudent($request->studentIdRef);
        $amount = (float) $request->amount;

        if ($amount <= 0) {
            throw new WalletSettlementFailedException('Debit amount must be greater than zero.');
        }

        $wallet = UserWallet::query()->firstOrCreate(
            ['user_id' => $student->id],
            ['balance' => 0, 'total_credited' => 0, 'total_debited' => 0],
        );

        if (! $wallet->hasBalance($amount)) {
            throw new InsufficientWalletBalanceException(
                sprintf(
                    'Insufficient wallet balance. Required: %s, Available: %s.',
                    number_format($amount, 2, '.', ''),
                    number_format((float) $wallet->balance, 2, '.', ''),
                )
            );
        }

        $description = $this->debitDescription($request);

        return DB::transaction(function () use ($request, $wallet, $amount, $description, $existing) {
            $walletTx = $wallet->fresh()->debit($amount, $description);
            $walletTx->update([
                'source_module' => 'canteen',
                'source_id' => $request->saleId,
            ]);

            $settlement = $existing
                ? tap($existing)->update([
                    'sale_id' => $request->saleId,
                    'student_id_ref' => $request->studentIdRef,
                    'transaction_type' => 'debit',
                    'amount' => $request->amount,
                    'currency' => $request->currency,
                    'status' => 'posted',
                    'source_module' => 'canteen',
                    'external_wallet_tx_id' => $walletTx->id,
                    'failure_reason' => null,
                    'posted_at' => now(),
                    'metadata' => $request->metadata,
                    'updated_by' => auth()->id(),
                ])
                : WalletReadyTransaction::query()->create([
                    'sale_id' => $request->saleId,
                    'student_id_ref' => $request->studentIdRef,
                    'transaction_type' => 'debit',
                    'amount' => $request->amount,
                    'currency' => $request->currency,
                    'status' => 'posted',
                    'source_module' => 'canteen',
                    'external_wallet_tx_id' => $walletTx->id,
                    'idempotency_key' => $request->idempotencyKey,
                    'posted_at' => now(),
                    'metadata' => $request->metadata,
                ]);

            return new WalletDebitResult(
                $settlement->id,
                $settlement->status,
                $settlement->source_module,
            );
        });
    }

    public function cancelDebit(string $idempotencyKey): void
    {
        DB::transaction(function () use ($idempotencyKey) {
            $settlement = WalletReadyTransaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if (! $settlement || $settlement->status === 'cancelled') {
                return;
            }

            if ($settlement->status === 'posted' && $settlement->external_wallet_tx_id) {
                $this->refundPostedDebit($settlement);
            }

            $settlement->update([
                'status' => 'cancelled',
                'updated_by' => auth()->id(),
            ]);
        });
    }

    protected function refundPostedDebit(WalletReadyTransaction $settlement): void
    {
        $walletTx = UserWalletTransaction::query()->find($settlement->external_wallet_tx_id);

        if (! $walletTx) {
            throw new WalletSettlementFailedException(
                "Core wallet transaction [{$settlement->external_wallet_tx_id}] not found for refund."
            );
        }

        $wallet = UserWallet::query()->find($walletTx->wallet_id);

        if (! $wallet) {
            throw new WalletSettlementFailedException(
                "Wallet [{$walletTx->wallet_id}] not found for refund."
            );
        }

        $refundTx = $wallet->credit(
            (float) $walletTx->amount,
            $this->refundDescription($settlement),
        );

        $refundTx->update([
            'source_module' => 'canteen',
            'source_id' => $settlement->sale_id,
        ]);
    }

    protected function resolveStudent(string $studentIdRef): User
    {
        $ref = trim($studentIdRef);

        if ($ref === '') {
            throw new WalletSettlementFailedException('Student reference is required for wallet debit.');
        }

        $student = ctype_digit($ref)
            ? User::query()->students()->find((int) $ref)
            : User::query()->students()->where('student_code', $ref)->first();

        if (! $student) {
            throw new WalletSettlementFailedException("Student not found for wallet debit reference [{$studentIdRef}].");
        }

        return $student;
    }

    protected function debitDescription(WalletDebitRequest $request): string
    {
        $saleNumber = $request->metadata['sale_number'] ?? null;

        return $saleNumber
            ? "Canteen purchase {$saleNumber}"
            : "Canteen purchase {$request->saleId}";
    }

    protected function refundDescription(WalletReadyTransaction $settlement): string
    {
        $saleNumber = $settlement->metadata['sale_number'] ?? null;

        return $saleNumber
            ? "Canteen refund {$saleNumber}"
            : "Canteen refund {$settlement->sale_id}";
    }
}
