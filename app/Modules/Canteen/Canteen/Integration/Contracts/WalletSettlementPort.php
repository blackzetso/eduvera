<?php

namespace App\Modules\Canteen\Integration\Contracts;

use App\Modules\Canteen\Integration\DTOs\WalletDebitRequest;
use App\Modules\Canteen\Integration\DTOs\WalletDebitResult;

interface WalletSettlementPort
{
    public function requestDebit(WalletDebitRequest $request): WalletDebitResult;

    public function cancelDebit(string $idempotencyKey): void;
}
