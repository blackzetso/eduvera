<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\UserWallet;
use App\Models\UserWalletTransaction;
use App\Modules\Canteen\Integration\Adapters\UserWalletSettlementAdapter;
use App\Modules\Canteen\Integration\DTOs\WalletDebitRequest;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Models\WalletReadyTransaction;

$studentId = 163;
$saleIdArg = $argv[1] ?? null;

echo "wallet_adapter=".config('canteen.integration.wallet_adapter')."\n\n";

$wallet = UserWallet::query()->where('user_id', $studentId)->first();
if (! $wallet) {
    echo "No wallet for student {$studentId}\n";
    exit(1);
}

echo "=== Student {$studentId} wallet ===\n";
echo "Balance: {$wallet->balance}\n";
echo "Total credited: {$wallet->total_credited}\n";
echo "Total debited: {$wallet->total_debited}\n\n";

echo "=== Canteen sales for this student ===\n";
$sales = Sale::query()
    ->where(fn ($q) => $q
        ->where('student_user_id', $studentId)
        ->orWhere('student_id_ref', (string) $studentId))
    ->orderByDesc('sold_at')
    ->limit(10)
    ->get(['id', 'sale_number', 'status', 'total', 'payment_method', 'sold_at']);

foreach ($sales as $sale) {
    echo "- {$sale->id} | {$sale->sale_number} | {$sale->status} | total={$sale->total} | {$sale->sold_at}\n";
}

if ($sales->isEmpty()) {
    echo "(no sales found)\n";
}

$saleId = $saleIdArg ?: $sales->first()?->id;

if ($saleId) {
    echo "\n=== Checking sale {$saleId} ===\n";
    $sale = Sale::query()->find($saleId);
    if ($sale) {
        echo "Sale status: {$sale->status}, total: {$sale->total}, payment: {$sale->payment_method}\n";

        $settlement = WalletReadyTransaction::query()->where('sale_id', $saleId)->first();
        if ($settlement) {
            echo "WalletReadyTransaction: status={$settlement->status}, amount={$settlement->amount}, external_tx={$settlement->external_wallet_tx_id}\n";
        } else {
            echo "WalletReadyTransaction: NOT FOUND (pending adapter or failed checkout)\n";
        }

        $tx = UserWalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('source_module', 'canteen')
            ->where('source_id', (string) $saleId)
            ->first();

        if ($tx) {
            echo "UserWalletTransaction: type={$tx->type}, amount={$tx->amount}, desc={$tx->description}\n";
        } else {
            echo "UserWalletTransaction for this sale: NOT FOUND\n";
        }
    } else {
        echo "Sale not found.\n";
    }
} else {
    echo "\n(No sale id — pass UUID as argv[1] to inspect a specific sale)\n";
}

echo "\n=== Recent canteen wallet transactions ===\n";
$recent = UserWalletTransaction::query()
    ->where('wallet_id', $wallet->id)
    ->where('source_module', 'canteen')
    ->orderByDesc('id')
    ->limit(5)
    ->get();

foreach ($recent as $tx) {
    echo "- id={$tx->id} | {$tx->type} | {$tx->amount} | source_id={$tx->source_id} | {$tx->description}\n";
}

if ($recent->isEmpty()) {
    echo "(no canteen debits in user_wallet_transactions — adapter is likely 'pending')\n";
}

echo "\n=== Dry-run adapter test (1.00 EGP, will NOT execute unless --debit flag) ===\n";
if (in_array('--debit', $argv, true) && $saleId) {
    $adapter = app(UserWalletSettlementAdapter::class);
    $balanceBefore = (string) $wallet->fresh()->balance;

    try {
        $result = $adapter->requestDebit(new WalletDebitRequest(
            saleId: (string) $saleId,
            studentIdRef: (string) $studentId,
            amount: '1.00',
            currency: 'EGP',
            idempotencyKey: 'TEST-'.time(),
            metadata: ['sale_number' => 'TEST'],
        ));
        $balanceAfter = (string) UserWallet::query()->where('user_id', $studentId)->value('balance');
        echo "Debit OK | settlement={$result->settlementId} status={$result->status}\n";
        echo "Balance before: {$balanceBefore} | after: {$balanceAfter}\n";
    } catch (Throwable $e) {
        echo "Debit failed: ".$e->getMessage()."\n";
    }
} else {
    echo "Skipped. Run: php scripts/wallet-audit-student-163.php [sale-uuid] --debit\n";
}
