<?php

namespace App\Modules\Canteen\Listeners;

use App\Jobs\Canteen\NotifyAdminsOfCanteenSettlementFailure;
use App\Modules\Canteen\Events\CanteenSaleFailed;

class DispatchCanteenAdminFailureNotifications
{
    public function handle(CanteenSaleFailed $event): void
    {
        if (! config('canteen.notifications.admin_failures_enabled', true)) {
            return;
        }

        NotifyAdminsOfCanteenSettlementFailure::dispatch($event->sale->id, $event->reason);
    }
}
