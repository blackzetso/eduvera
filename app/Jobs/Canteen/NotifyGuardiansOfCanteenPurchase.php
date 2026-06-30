<?php

namespace App\Jobs\Canteen;

use App\Modules\Canteen\Services\CanteenNotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyGuardiansOfCanteenPurchase implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $saleId) {}

    public function handle(CanteenNotificationDispatchService $dispatch): void
    {
        $dispatch->dispatchForSaleId($this->saleId);
    }
}
