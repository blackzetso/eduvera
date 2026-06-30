<?php

namespace App\Modules\Canteen\Integration\Contracts;

interface ParentNotificationPort
{
    public function queueSaleVisibility(string $saleId, string $studentIdRef, array $payload): void;

    public function reverseSaleVisibility(string $saleId): void;
}
