<?php

namespace App\Modules\Canteen\Integration\DTOs;

readonly class WalletDebitRequest
{
    public function __construct(
        public string $saleId,
        public string $studentIdRef,
        public string $amount,
        public string $currency,
        public string $idempotencyKey,
        public ?array $metadata = null,
    ) {}
}
