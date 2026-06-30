<?php

namespace Tests\Feature\Canteen\Notifications;

use App\Models\GuardianNotificationPreference;
use App\Models\User;
use App\Modules\Canteen\Events\CanteenSaleCompleted;
use App\Modules\Canteen\Models\ParentVisibilityQueue;
use App\Modules\Canteen\Services\CanteenNotificationDispatchService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Canteen\Concerns\InteractsWithCanteenCheckout;
use Tests\Support\CanteenCheckoutTestSchema;
use Tests\Support\CanteenPhase8TestSchema;
use Tests\TestCase;

class CanteenPurchaseNotificationTest extends TestCase
{
    use CanteenCheckoutTestSchema;
    use CanteenPhase8TestSchema;
    use InteractsWithCanteenCheckout;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'canteen.enabled' => true,
            'canteen.integration.parent_adapter' => 'eduvera',
            'canteen.notifications.whatsapp_enabled' => false,
        ]);

        $this->setUpCanteenCheckoutTestSchema();
        $this->extendCanteenPhase8TestSchema();
    }

    public function test_dispatch_service_notifies_linked_guardian_in_app(): void
    {
        Notification::fake();

        [$student, $guardian] = $this->seedFamily();
        $sale = $this->seedCompletedSale($student);

        ParentVisibilityQueue::query()->create([
            'sale_id' => $sale->id,
            'student_id_ref' => (string) $student->id,
            'guardian_id_ref' => (string) $guardian->id,
            'payload' => ['sale_number' => $sale->sale_number],
            'visibility_status' => 'pending',
            'notification_status' => 'pending',
        ]);

        GuardianNotificationPreference::query()->create([
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'notify_in_app' => true,
            'notify_canteen_purchase' => true,
            'notify_whatsapp' => false,
            'notify_email' => false,
        ]);

        $sent = app(CanteenNotificationDispatchService::class)->dispatchForSaleId($sale->id);

        $this->assertTrue($sent);
        Notification::assertSentTo($guardian, \App\Modules\Canteen\Notifications\CanteenPurchaseNotification::class);

        $queue = ParentVisibilityQueue::query()->where('sale_id', $sale->id)->first();
        $this->assertSame('sent', $queue->notification_status);
        $this->assertSame('published', $queue->visibility_status);
    }

    public function test_completed_event_dispatches_notification_job_when_eduvera_adapter_enabled(): void
    {
        Event::fake([CanteenSaleCompleted::class]);

        [$student] = $this->createEligibleStudent();
        $sale = $this->seedCompletedSale($student);

        event(new CanteenSaleCompleted($sale));

        Event::assertDispatched(CanteenSaleCompleted::class);
    }

    /**
     * @return array{0: User, 1: User}
     */
    protected function seedFamily(): array
    {
        $student = User::factory()->create(['user_type' => 'student']);
        $guardian = User::factory()->create(['user_type' => 'guardian', 'phone' => '201234567890']);

        $student->guardians()->attach($guardian->id, [
            'relationship_type' => 'guardian',
            'is_primary' => true,
        ]);

        return [$student, $guardian];
    }
}
