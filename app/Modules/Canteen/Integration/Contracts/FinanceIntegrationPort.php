<?php

namespace App\Modules\Canteen\Integration\Contracts;

use App\Modules\Canteen\Models\Sale;

interface FinanceIntegrationPort
{
    public function recordSaleCompleted(Sale $sale): void;

    public function recordSaleFailed(Sale $sale, string $reason): void;

    public function recordSaleVoided(Sale $sale): void;
}
