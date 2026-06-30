<?php

namespace App\Modules\Canteen\Integration\Adapters;

use App\Modules\Canteen\Integration\Contracts\ParentNotificationPort;
use App\Modules\Canteen\Models\ParentVisibilityQueue;

class QueuedParentNotificationAdapter implements ParentNotificationPort
{
    public function queueSaleVisibility(string $saleId, string $studentIdRef, array $payload): void
    {
        ParentVisibilityQueue::query()->firstOrCreate(
            ['sale_id' => $saleId],
            [
                'student_id_ref' => $studentIdRef,
                'payload' => $payload,
                'visibility_status' => 'pending',
                'notification_status' => 'pending',
            ],
        );
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
