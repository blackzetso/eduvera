<?php

namespace Tests\Feature\Canteen;

use App\Models\UserWallet;
use App\Modules\Canteen\Integration\Adapters\PendingWalletSettlementAdapter;
use App\Modules\Canteen\Integration\Contracts\WalletSettlementPort;
use App\Modules\Canteen\Models\SaleItem;
use App\Modules\Canteen\Models\WalletReadyTransaction;
use App\Modules\Canteen\Services\PosCheckoutService;
use App\Modules\Canteen\Support\SaleStatus;
use Tests\Feature\Canteen\Concerns\InteractsWithCanteenCheckout;
use Tests\Support\CanteenCheckoutTestSchema;
use Tests\TestCase;

class CheckoutWalletIntegrationTest extends TestCase
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
        ]);
        $this->setUpCanteenCheckoutTestSchema();
        $this->bindUserWalletAdapter();
    }

    public function test_successful_checkout_debits_wallet_reduces_inventory_and_completes_sale(): void
    {
        [$student] = $this->createEligibleStudent(walletBalance: 50.00);
        $product = $this->createProduct(price: 12.50);
        $cashier = $this->createCashier();
        $stockBefore = $this->inventoryOnHand($product->id);

        $sale = app(PosCheckoutService::class)->checkout(
            $this->checkoutPayload((string) $student->id, $product, '2'),
            $cashier->id,
        );

        $this->assertSame(SaleStatus::COMPLETED, $sale->status);
        $this->assertNotNull($sale->completed_at);
        $this->assertSame('25.00', (string) $sale->total);
        $this->assertSame(1, SaleItem::query()->where('sale_id', $sale->id)->count());

        $wallet = UserWallet::query()->where('user_id', $student->id)->first();
        $this->assertSame('25.00', (string) $wallet->fresh()->balance);

        $this->assertEquals(
            (float) bcsub($stockBefore, '2', 3),
            (float) $this->inventoryOnHand($product->id),
        );

        $settlement = WalletReadyTransaction::query()->where('sale_id', $sale->id)->first();
        $this->assertNotNull($settlement);
        $this->assertSame('posted', $settlement->status);
        $this->assertNotNull($settlement->external_wallet_tx_id);
        $this->assertArrayHasKey('wallet_settlement', $sale->fresh()->metadata ?? []);
    }

    public function test_pending_wallet_adapter_still_completes_checkout_without_core_wallet_debit(): void
    {
        $this->app->singleton(WalletSettlementPort::class, fn () => new PendingWalletSettlementAdapter);

        [$student] = $this->createEligibleStudent(walletBalance: 50.00);
        $product = $this->createProduct(price: 5.00);
        $cashier = $this->createCashier();
        $walletBefore = (string) UserWallet::query()->where('user_id', $student->id)->value('balance');

        $sale = app(PosCheckoutService::class)->checkout(
            $this->checkoutPayload((string) $student->id, $product),
            $cashier->id,
        );

        $this->assertSame(SaleStatus::COMPLETED, $sale->status);

        $settlement = WalletReadyTransaction::query()->where('sale_id', $sale->id)->first();
        $this->assertSame('posted', $settlement->status);
        $this->assertNull($settlement->external_wallet_tx_id);
        $this->assertSame($walletBefore, (string) UserWallet::query()->where('user_id', $student->id)->value('balance'));
        $this->assertSame(1, SaleItem::query()->where('sale_id', $sale->id)->count());
    }
}
