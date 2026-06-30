<?php

namespace App\Modules\Canteen\Events;

use App\Modules\Canteen\Models\Sale;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CanteenSaleCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Sale $sale) {}
}
