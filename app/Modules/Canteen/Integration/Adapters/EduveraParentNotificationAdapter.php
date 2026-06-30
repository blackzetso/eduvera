<?php

namespace App\Modules\Canteen\Integration\Adapters;

use App\Modules\Canteen\Integration\Contracts\ParentNotificationPort;
use App\Modules\Canteen\Models\ParentVisibilityQueue;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Services\CanteenPurchaseGuardianSyncService;

class EduveraParentNotificationAdapter implements ParentNotificationPort
{
    public function __construct(protected CanteenPurchaseGuardianSyncService $guardianSync) {}

    public function queueSaleVisibility(string $saleId, string $studentIdRef, array $payload): void
    {
        $queue = ParentVisibilityQueue::query()->firstOrCreate(
            ['sale_id' => $saleId],
            [
                'student_id_ref' => $studentIdRef,
                'payload' => $payload,
                'visibility_status' => 'pending',
                'notification_status' => 'pending',
            ],
        );

        $sale = Sale::query()->find($saleId);

        if ($sale) {
            $this->guardianSync->syncSale($sale);
            $queue->refresh();

            if (! $queue->guardian_id_ref && $sale->primary_guardian_user_id) {
                $queue->update(['guardian_id_ref' => (string) $sale->primary_guardian_user_id]);
            }
        }

        if ($queue->notification_status === 'none') {
            $queue->update(['notification_status' => 'pending']);
        }
    }

    public function reverseSaleVisibility(string $saleId): void
    {
        ParentVisibilityQueue::query()
            ->where('sale_id', $saleId)
            ->where('visibility_status', '!=', 'suppressed')
            ->update([
                'visibility_status' => 'suppressed',
                'notification_status' => 'suppressed',
            ]);
    }
}
