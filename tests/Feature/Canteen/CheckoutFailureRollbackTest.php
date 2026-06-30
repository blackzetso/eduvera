<?php

namespace Tests\Feature\Canteen;

use App\Models\UserWallet;
use App\Modules\Canteen\Exceptions\WalletSettlementFailedException;
use App\Modules\Canteen\Integration\Contracts\WalletSettlementPort;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Models\SaleItem;
use App\Modules\Canteen\Models\WalletReadyTransaction;
use App\Modules\Canteen\Services\PosCheckoutService;
use App\Modules\Canteen\Services\StudentBlockService;
use App\Modules\Canteen\Support\SaleStatus;
use InvalidArgumentException;
use Mockery;
use Tests\Feature\Canteen\Concerns\InteractsWithCanteenCheckout;
use Tests\Support\CanteenCheckoutTestSchema;
use Tests\TestCase;

class CheckoutFailureRollbackTest extends TestCase
{
    use CanteenCheckoutTestSchema;
    use InteractsWithCanteenCheckout;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'canteen.enabled' => true,
            'canteen.integration.parent_adapter' => 'queued',
            'canteen.integration.finance_adapter' => 'noop',
            'canteen.notifications.admin_failures_enabled' => false,
        ]);
        $this->setUpCanteenCheckoutTestSchema();
        $this->bindUserWalletAdapter();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_insufficient_balance_leaves_wallet_inventory_unchanged_and_marks_sale_failed(): void
    {
        [$student] = $this->createEligibleStudent(walletBalance: 5.00);
        $product = $this->createProduct(price: 20.00);
        $cashier = $this->createCashier();
        $stockBefore = $this->inventoryOnHand($product->id);

        $sale = app(PosCheckoutService::class)->checkout(
            $this->checkoutPayload((string) $student->id, $product),
            $cashier->id,
        );

        $this->assertSame(SaleStatus::FAILED, $sale->status);
        $this->assertSame('5.00', (string) UserWallet::query()->where('user_id', $student->id)->value('balance'));
        $this->assertSame($stockBefore, $this->inventoryOnHand($product->id));
        $this->assertSame(0, SaleItem::query()->count());
        $this->assertSame(0, WalletReadyTransaction::query()->count());
    }

    public function test_settlement_failure_leaves_inventory_unchanged_and_marks_sale_failed(): void
    {
        $wallet = Mockery::mock(WalletSettlementPort::class);
        $wallet->shouldReceive('requestDebit')
            ->once()
            ->andThrow(new WalletSettlementFailedException('Settlement gateway unavailable.'));
        $this->app->instance(WalletSettlementPort::class, $wallet);

        [$student] = $this->createEligibleStudent(walletBalance: 100.00);
        $product = $this->createProduct(price: 15.00);
        $cashier = $this->createCashier();
        $stockBefore = $this->inventoryOnHand($product->id);

        $sale = app(PosCheckoutService::class)->checkout(
            $this->checkoutPayload((string) $student->id, $product),
            $cashier->id,
        );

        $this->assertSame(SaleStatus::FAILED, $sale->status);
        $this->assertStringContainsString('Settlement gateway unavailable', $sale->fresh()->metadata['wallet_settlement_failure']['reason'] ?? '');
        $this->assertSame('100.00', (string) UserWallet::query()->where('user_id', $student->id)->value('balance'));
        $this->assertSame($stockBefore, $this->inventoryOnHand($product->id));
        $this->assertSame(0, SaleItem::query()->count());
    }

    public function test_daily_limit_exceeded_prevents_debit_and_inventory_movement(): void
    {
        [$student, $profile] = $this->createEligibleStudent(walletBalance: 100.00);
        $profile->update(['daily_spending_limit' => '10.00']);
        $this->seedCompletedSale($student, '8.00');

        $product = $this->createProduct(price: 5.00);
        $cashier = $this->createCashier();
        $stockBefore = $this->inventoryOnHand($product->id);
        $walletBefore = (string) UserWallet::query()->where('user_id', $student->id)->value('balance');

        try {
            app(PosCheckoutService::class)->checkout(
                $this->checkoutPayload((string) $student->id, $product),
                $cashier->id,
            );
            $this->fail('Expected daily limit exception was not thrown.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Daily spending limit exceeded', $e->getMessage());
        }

        $this->assertSame($walletBefore, (string) UserWallet::query()->where('user_id', $student->id)->value('balance'));
        $this->assertSame($stockBefore, $this->inventoryOnHand($product->id));
        $this->assertSame(1, Sale::query()->where('status', SaleStatus::COMPLETED)->count());
        $this->assertSame(0, SaleItem::query()->count());
    }

    public function test_blocked_student_prevents_debit_and_inventory_movement(): void
    {
        [$student] = $this->createEligibleStudent(walletBalance: 100.00);
        $product = $this->createProduct(price: 8.00);
        $cashier = $this->createCashier();
        $stockBefore = $this->inventoryOnHand($product->id);
        $walletBefore = (string) UserWallet::query()->where('user_id', $student->id)->value('balance');

        app(StudentBlockService::class)->blockProduct((string) $student->id, $product->id, [
            'reason' => 'Parent request',
        ]);

        try {
            app(PosCheckoutService::class)->checkout(
                $this->checkoutPayload((string) $student->id, $product),
                $cashier->id,
            );
            $this->fail('Expected restriction block exception was not thrown.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Purchase blocked', $e->getMessage());
        }

        $this->assertSame($walletBefore, (string) UserWallet::query()->where('user_id', $student->id)->value('balance'));
        $this->assertSame($stockBefore, $this->inventoryOnHand($product->id));
        $this->assertSame(0, Sale::query()->where('status', SaleStatus::PENDING_PAYMENT)->count());
        $this->assertSame(0, SaleItem::query()->count());
    }
}
