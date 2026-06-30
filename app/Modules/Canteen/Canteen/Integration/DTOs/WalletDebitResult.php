<?php

namespace App\Modules\Canteen\Integration\DTOs;

readonly class WalletDebitResult
{
    public function __construct(
        public string $transactionId,
        public string $status,
        public string $sourceModule,
    ) {}
}
