<?php

namespace Tests\Feature\Canteen\Notifications;

use App\Jobs\Canteen\NotifyAdminsOfCanteenSettlementFailure;
use App\Models\User;
use App\Modules\Canteen\Events\CanteenSaleFailed;
use App\Modules\Canteen\Support\CanteenPermission;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Canteen\Concerns\InteractsWithCanteenCheckout;
use Tests\Support\CanteenCheckoutTestSchema;
use Tests\Support\CanteenPhase8TestSchema;
use Tests\TestCase;

class CanteenAdminFailureNotificationTest extends TestCase
{
    use CanteenCheckoutTestSchema;
    use CanteenPhase8TestSchema;
    use InteractsWithCanteenCheckout;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'canteen.enabled' => true,
            'canteen.notifications.admin_failures_enabled' => true,
        ]);

        $this->setUpCanteenCheckoutTestSchema();
        $this->extendCanteenPhase8TestSchema();
    }

    public function test_admin_failure_job_notifies_canteen_admin_users(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'user_type' => 'admin',
            'email' => 'canteen-admin@example.com',
        ]);

        [$student] = $this->createEligibleStudent();
        $sale = $this->seedCompletedSale($student);
        $sale->update(['status' => 'failed', 'metadata' => ['wallet_settlement_failure' => ['reason' => 'Insufficient balance']]]);

        (new NotifyAdminsOfCanteenSettlementFailure($sale->id, 'Insufficient balance'))->handle();

        Notification::assertSentTo($admin, \App\Modules\Canteen\Notifications\CanteenSettlementFailedNotification::class);
    }

    public function test_failed_event_triggers_admin_notification_listener(): void
    {
        Notification::fake();

        User::factory()->create(['user_type' => 'admin']);

        [$student] = $this->createEligibleStudent();
        $sale = $this->seedCompletedSale($student);
        $sale->update(['status' => 'failed']);

        event(new CanteenSaleFailed($sale->fresh(), 'Insufficient wallet balance'));

        Notification::assertSentTo(
            User::query()->where('user_type', 'admin')->first(),
            \App\Modules\Canteen\Notifications\CanteenSettlementFailedNotification::class,
        );
    }
}
