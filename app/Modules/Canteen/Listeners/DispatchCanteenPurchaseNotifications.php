<?php

namespace App\Modules\Canteen\Listeners;

use App\Jobs\Canteen\NotifyGuardiansOfCanteenPurchase;
use App\Modules\Canteen\Events\CanteenSaleCompleted;

class DispatchCanteenPurchaseNotifications
{
    public function handle(CanteenSaleCompleted $event): void
    {
        if (config('canteen.integration.parent_adapter') !== 'eduvera') {
            return;
        }

        NotifyGuardiansOfCanteenPurchase::dispatch($event->sale->id);
    }
}
