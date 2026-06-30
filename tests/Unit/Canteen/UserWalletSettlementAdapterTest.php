<?php

namespace Tests\Unit\Canteen;

use App\Models\User;
use App\Models\UserWallet;
use App\Models\UserWalletTransaction;
use App\Modules\Canteen\Exceptions\InsufficientWalletBalanceException;
use App\Modules\Canteen\Integration\Adapters\UserWalletSettlementAdapter;
use App\Modules\Canteen\Integration\DTOs\WalletDebitRequest;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Models\WalletReadyTransaction;
use Illuminate\Support\Str;
use Tests\Support\CanteenWalletTestSchema;
use Tests\TestCase;

class UserWalletSettlementAdapterTest extends TestCase
{
    use CanteenWalletTestSchema;

    protected UserWalletSettlementAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCanteenWalletTestSchema();
        $this->adapter = new UserWalletSettlementAdapter;
    }

    public function test_successful_debit_posts_wallet_and_settlement_records(): void
    {
        [$student, $cashier, $sale] = $this->createSaleContext(balance: 100.00);

        $request = new WalletDebitRequest(
            saleId: $sale->id,
            studentIdRef: (string) $student->id,
            amount: '25.50',
            currency: 'EGP',
            idempotencyKey: 'canteen-sale-'.$sale->id,
            metadata: ['sale_number' => $sale->sale_number],
        );

        $result = $this->adapter->requestDebit($request);

        $this->assertSame('posted', $result->status);
        $this->assertSame('canteen', $result->sourceModule);

        $wallet = UserWallet::query()->where('user_id', $student->id)->first();
        $this->assertSame('74.50', (string) $wallet->fresh()->balance);

        $settlement = WalletReadyTransaction::query()->find($result->transactionId);
        $this->assertNotNull($settlement);
        $this->assertSame('posted', $settlement->status);
        $this->assertNotNull($settlement->external_wallet_tx_id);
        $this->assertNotNull($settlement->posted_at);

        $walletTx = UserWalletTransaction::query()->find($settlement->external_wallet_tx_id);
        $this->assertNotNull($walletTx);
        $this->assertSame('debit', $walletTx->type);
        $this->assertSame('25.50', (string) $walletTx->amount);
        $this->assertSame('canteen', $walletTx->source_module);
        $this->assertSame($sale->id, $walletTx->source_id);
    }

    public function test_insufficient_balance_throws_without_wallet_or_settlement_side_effects(): void
    {
        [$student, , $sale] = $this->createSaleContext(balance: 10.00);

        $request = new WalletDebitRequest(
            saleId: $sale->id,
            studentIdRef: (string) $student->id,
            amount: '25.00',
            currency: 'EGP',
            idempotencyKey: 'canteen-sale-'.$sale->id,
        );

        try {
            $this->adapter->requestDebit($request);
            $this->fail('Expected InsufficientWalletBalanceException was not thrown.');
        } catch (InsufficientWalletBalanceException $e) {
            $this->assertStringContainsString('Insufficient wallet balance', $e->getMessage());
        }

        $this->assertSame('10.00', (string) UserWallet::query()->where('user_id', $student->id)->value('balance'));
        $this->assertDatabaseCount('canteen_wallet_ready_transactions', 0);
        $this->assertDatabaseCount('user_wallet_transactions', 0);
    }

    public function test_cancel_debit_refunds_wallet_and_marks_settlement_cancelled(): void
    {
        [$student, , $sale] = $this->createSaleContext(balance: 50.00);

        $idempotencyKey = 'canteen-sale-'.$sale->id;
        $request = new WalletDebitRequest(
            saleId: $sale->id,
            studentIdRef: (string) $student->id,
            amount: '20.00',
            currency: 'EGP',
            idempotencyKey: $idempotencyKey,
            metadata: ['sale_number' => $sale->sale_number],
        );

        $this->adapter->requestDebit($request);
        $this->adapter->cancelDebit($idempotencyKey);

        $wallet = UserWallet::query()->where('user_id', $student->id)->first();
        $this->assertSame('50.00', (string) $wallet->fresh()->balance);

        $settlement = WalletReadyTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
        $this->assertSame('cancelled', $settlement->status);

        $this->assertSame(2, UserWalletTransaction::query()->where('wallet_id', $wallet->id)->count());
        $this->assertTrue(
            UserWalletTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->where('type', 'credit')
                ->where('source_module', 'canteen')
                ->where('source_id', $sale->id)
                ->exists()
        );
    }

    public function test_idempotent_debit_returns_existing_posted_settlement(): void
    {
        [$student, , $sale] = $this->createSaleContext(balance: 80.00);

        $request = new WalletDebitRequest(
            saleId: $sale->id,
            studentIdRef: (string) $student->id,
            amount: '15.00',
            currency: 'EGP',
            idempotencyKey: 'canteen-sale-'.$sale->id,
        );

        $first = $this->adapter->requestDebit($request);
        $second = $this->adapter->requestDebit($request);

        $this->assertSame($first->transactionId, $second->transactionId);
        $this->assertSame('65.00', (string) UserWallet::query()->where('user_id', $student->id)->value('balance'));
        $this->assertDatabaseCount('user_wallet_transactions', 1);
        $this->assertDatabaseCount('canteen_wallet_ready_transactions', 1);
    }

    /**
     * @return array{0: User, 1: User, 2: Sale}
     */
    protected function createSaleContext(float $balance): array
    {
        $cashier = User::factory()->create([
            'user_type' => 'admin',
        ]);

        $student = User::factory()->create([
            'user_type' => 'student',
            'student_code' => 'STU-'.Str::upper(Str::random(6)),
        ]);

        UserWallet::query()->create([
            'user_id' => $student->id,
            'balance' => $balance,
            'total_credited' => $balance,
            'total_debited' => 0,
        ]);

        $sale = Sale::query()->create([
            'sale_number' => 'CN-TEST-'.Str::upper(Str::random(6)),
            'student_id_ref' => (string) $student->id,
            'student_user_id' => $student->id,
            'student_name' => $student->name,
            'subtotal' => '20.00',
            'discount' => '0',
            'total' => '20.00',
            'payment_method' => 'wallet_ready',
            'status' => 'pending_payment',
            'cashier_user_id' => $cashier->id,
            'sold_at' => now(),
        ]);

        return [$student, $cashier, $sale];
    }
}
