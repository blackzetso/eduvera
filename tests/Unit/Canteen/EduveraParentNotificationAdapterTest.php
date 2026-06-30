<?php

namespace Tests\Unit\Canteen;

use App\Modules\Canteen\Integration\Adapters\EduveraParentNotificationAdapter;
use App\Modules\Canteen\Models\ParentVisibilityQueue;
use App\Modules\Canteen\Services\CanteenPurchaseGuardianSyncService;
use Tests\Feature\Canteen\Concerns\InteractsWithCanteenCheckout;
use Tests\Support\CanteenCheckoutTestSchema;
use Tests\Support\CanteenPhase8TestSchema;
use Tests\TestCase;

class EduveraParentNotificationAdapterTest extends TestCase
{
    use CanteenCheckoutTestSchema;
    use CanteenPhase8TestSchema;
    use InteractsWithCanteenCheckout;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCanteenCheckoutTestSchema();
        $this->extendCanteenPhase8TestSchema();
    }

    public function test_queue_sale_visibility_creates_pending_queue_row_with_guardian_ref(): void
    {
        [$student] = $this->createEligibleStudent();
        $sale = $this->seedCompletedSale($student);

        $adapter = new EduveraParentNotificationAdapter(app(CanteenPurchaseGuardianSyncService::class));

        $adapter->queueSaleVisibility($sale->id, (string) $student->id, [
            'sale_number' => $sale->sale_number,
            'total' => (string) $sale->total,
        ]);

        $queue = ParentVisibilityQueue::query()->where('sale_id', $sale->id)->first();

        $this->assertNotNull($queue);
        $this->assertSame('pending', $queue->visibility_status);
        $this->assertSame('pending', $queue->notification_status);
    }

    public function test_reverse_sale_visibility_suppresses_queue_row(): void
    {
        [$student] = $this->createEligibleStudent();
        $sale = $this->seedCompletedSale($student);
        $adapter = new EduveraParentNotificationAdapter(app(CanteenPurchaseGuardianSyncService::class));

        $adapter->queueSaleVisibility($sale->id, (string) $student->id, []);
        $adapter->reverseSaleVisibility($sale->id);

        $queue = ParentVisibilityQueue::query()->where('sale_id', $sale->id)->first();
        $this->assertSame('suppressed', $queue->visibility_status);
        $this->assertSame('suppressed', $queue->notification_status);
    }
}
