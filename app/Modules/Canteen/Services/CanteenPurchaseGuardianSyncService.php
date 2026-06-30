<?php

namespace App\Modules\Canteen\Services;

use App\Modules\Canteen\Models\ParentVisibilityQueue;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Models\StudentProfile;
use App\Modules\Canteen\Support\SaleStatus;
use Illuminate\Support\Collection;

class CanteenPurchaseGuardianSyncService
{
    public function syncSale(Sale $sale): Sale
    {
        $guardianIdRef = $this->resolveGuardianIdRefForSale($sale);

        if (! $guardianIdRef) {
            return $sale;
        }

        $metadata = $sale->metadata ?? [];
        $metadata['primary_guardian_id_ref'] = $guardianIdRef;

        $sale->update([
            'primary_guardian_user_id' => ctype_digit($guardianIdRef) ? (int) $guardianIdRef : null,
            'metadata' => $metadata,
        ]);

        ParentVisibilityQueue::query()
            ->where('sale_id', $sale->id)
            ->whereNull('guardian_id_ref')
            ->update(['guardian_id_ref' => $guardianIdRef]);

        return $sale->fresh();
    }

    public function syncAllMissing(int $chunkSize = 200): int
    {
        $updated = 0;

        Sale::query()
            ->whereNull('primary_guardian_user_id')
            ->whereIn('status', [SaleStatus::COMPLETED, SaleStatus::PENDING_PAYMENT, SaleStatus::VOIDED])
            ->orderBy('sold_at')
            ->chunkById($chunkSize, function (Collection $sales) use (&$updated) {
                foreach ($sales as $sale) {
                    $before = $sale->primary_guardian_user_id;
                    $this->syncSale($sale);
                    if ($sale->fresh()->primary_guardian_user_id !== $before) {
                        $updated++;
                    }
                }
            });

        return $updated;
    }

    protected function resolveGuardianIdRefForSale(Sale $sale): ?string
    {
        if ($sale->primary_guardian_user_id) {
            return (string) $sale->primary_guardian_user_id;
        }

        $profile = StudentProfile::query()
            ->where(fn ($q) => $q
                ->when($sale->student_user_id, fn ($inner) => $inner->where('user_id', $sale->student_user_id))
                ->orWhere('student_id_ref', $sale->student_id_ref))
            ->first();

        return $profile?->guardian_id_ref
            ?? $profile?->metadata['guardian_id_ref'] ?? null;
    }
}
