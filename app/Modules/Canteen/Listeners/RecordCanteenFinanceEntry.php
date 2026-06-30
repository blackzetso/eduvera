<?php

namespace App\Modules\Canteen\Listeners;

use App\Modules\Canteen\Events\CanteenSaleCompleted;
use App\Modules\Canteen\Events\CanteenSaleFailed;
use App\Modules\Canteen\Events\CanteenSaleVoided;
use App\Modules\Canteen\Integration\Contracts\FinanceIntegrationPort;

class RecordCanteenFinanceEntry
{
    public function __construct(protected FinanceIntegrationPort $finance) {}

    public function handleCompleted(CanteenSaleCompleted $event): void
    {
        $this->finance->recordSaleCompleted($event->sale);
    }

    public function handleFailed(CanteenSaleFailed $event): void
    {
        $this->finance->recordSaleFailed($event->sale, $event->reason);
    }

    public function handleVoided(CanteenSaleVoided $event): void
    {
        $this->finance->recordSaleVoided($event->sale);
    }
}
