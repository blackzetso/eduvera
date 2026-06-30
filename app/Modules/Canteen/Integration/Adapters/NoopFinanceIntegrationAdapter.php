<?php

namespace App\Modules\Canteen\Integration\Adapters;

use App\Modules\Canteen\Integration\Contracts\FinanceIntegrationPort;
use App\Modules\Canteen\Models\Sale;

class NoopFinanceIntegrationAdapter implements FinanceIntegrationPort
{
    public function recordSaleCompleted(Sale $sale): void {}

    public function recordSaleFailed(Sale $sale, string $reason): void {}

    public function recordSaleVoided(Sale $sale): void {}
}
