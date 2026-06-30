<?php

namespace App\Modules\Canteen\Integration\Adapters;

use App\Models\User;
use App\Modules\Canteen\Integration\Contracts\FinanceIntegrationPort;
use App\Modules\Canteen\Integration\Contracts\GuardianIntegrationPort;
use App\Modules\Canteen\Models\CanteenFinanceEntry;
use App\Modules\Canteen\Models\InventoryTransaction;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Services\CanteenSettingsService;

class EduveraFinanceIntegrationAdapter implements FinanceIntegrationPort
{
    public function __construct(
        protected GuardianIntegrationPort $guardians,
        protected CanteenSettingsService $settings,
    ) {}

    public function recordSaleCompleted(Sale $sale): void
    {
        $sale->loadMissing(['items', 'walletReadyTransaction']);

        $this->upsertEntry(
            $sale,
            CanteenFinanceEntry::TYPE_PURCHASE,
            CanteenFinanceEntry::SCOPE_STUDENT,
            CanteenFinanceEntry::DIRECTION_DEBIT,
            CanteenFinanceEntry::STATUS_POSTED,
        );

        if ($this->resolveGuardianUserId($sale)) {
            $this->upsertEntry(
                $sale,
                CanteenFinanceEntry::TYPE_PURCHASE,
                CanteenFinanceEntry::SCOPE_FAMILY,
                CanteenFinanceEntry::DIRECTION_DEBIT,
                CanteenFinanceEntry::STATUS_POSTED,
            );
        }
    }

    public function recordSaleFailed(Sale $sale, string $reason): void
    {
        $sale->loadMissing(['walletReadyTransaction']);

        $this->upsertEntry(
            $sale,
            CanteenFinanceEntry::TYPE_FAILED,
            CanteenFinanceEntry::SCOPE_STUDENT,
            CanteenFinanceEntry::DIRECTION_DEBIT,
            CanteenFinanceEntry::STATUS_FAILED,
            metadata: ['failure_reason' => $reason],
            includeInventory: false,
        );
    }

    public function recordSaleVoided(Sale $sale): void
    {
        $sale->loadMissing(['items', 'walletReadyTransaction']);

        CanteenFinanceEntry::query()
            ->where('sale_id', $sale->id)
            ->where('entry_type', CanteenFinanceEntry::TYPE_PURCHASE)
            ->where('status', CanteenFinanceEntry::STATUS_POSTED)
            ->update(['status' => CanteenFinanceEntry::STATUS_REVERSED]);

        $this->upsertEntry(
            $sale,
            CanteenFinanceEntry::TYPE_VOID,
            CanteenFinanceEntry::SCOPE_STUDENT,
            CanteenFinanceEntry::DIRECTION_CREDIT,
            CanteenFinanceEntry::STATUS_POSTED,
        );

        if ($this->resolveGuardianUserId($sale)) {
            $this->upsertEntry(
                $sale,
                CanteenFinanceEntry::TYPE_VOID,
                CanteenFinanceEntry::SCOPE_FAMILY,
                CanteenFinanceEntry::DIRECTION_CREDIT,
                CanteenFinanceEntry::STATUS_POSTED,
            );
        }
    }

    protected function upsertEntry(
        Sale $sale,
        string $entryType,
        string $ledgerScope,
        string $direction,
        string $status,
        array $metadata = [],
        bool $includeInventory = true,
    ): CanteenFinanceEntry {
        $walletTx = $sale->walletReadyTransaction;
        $guardianUserId = $ledgerScope === CanteenFinanceEntry::SCOPE_FAMILY
            ? $this->resolveGuardianUserId($sale)
            : null;

        $payload = [
            'student_user_id' => $sale->student_user_id,
            'student_id_ref' => $sale->student_id_ref,
            'guardian_user_id' => $guardianUserId,
            'household_key' => $guardianUserId ? (string) $guardianUserId : null,
            'amount' => $sale->total,
            'direction' => $direction,
            'currency' => $walletTx?->currency ?? $this->settings->currency(),
            'wallet_settlement_id' => $walletTx?->id,
            'wallet_tx_id' => $walletTx?->external_wallet_tx_id,
            'inventory_transaction_ids' => $includeInventory ? $this->inventoryIdsForSale($sale->id) : [],
            'status' => $status,
            'metadata' => array_merge([
                'sale_number' => $sale->sale_number,
                'payment_method' => $sale->payment_method,
                'item_count' => $sale->items->count(),
            ], $metadata),
            'posted_at' => $sale->completed_at ?? $sale->voided_at ?? $sale->sold_at ?? now(),
        ];

        return CanteenFinanceEntry::query()->updateOrCreate(
            [
                'sale_id' => $sale->id,
                'ledger_scope' => $ledgerScope,
                'entry_type' => $entryType,
            ],
            $payload,
        );
    }

    protected function resolveGuardianUserId(Sale $sale): ?int
    {
        if ($sale->primary_guardian_user_id) {
            return $sale->primary_guardian_user_id;
        }

        if (! $sale->student_user_id) {
            return null;
        }

        $student = User::query()->students()->find($sale->student_user_id);

        if (! $student) {
            return null;
        }

        $primary = $this->guardians->resolvePrimaryGuardian($student);

        return $primary && ctype_digit($primary->guardianIdRef)
            ? (int) $primary->guardianIdRef
            : null;
    }

    /**
     * @return list<string>
     */
    protected function inventoryIdsForSale(string $saleId): array
    {
        return InventoryTransaction::query()
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->pluck('id')
            ->all();
    }
}
