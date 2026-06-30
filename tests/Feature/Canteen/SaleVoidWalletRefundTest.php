<?php

namespace Tests\Feature\Canteen;

use App\Models\UserWallet;
use App\Models\UserWalletTransaction;
use App\Modules\Canteen\Models\WalletReadyTransaction;
use App\Modules\Canteen\Services\PosCheckoutService;
use App\Modules\Canteen\Services\SaleVoidService;
use App\Modules\Canteen\Support\SaleStatus;
use Tests\Feature\Canteen\Concerns\InteractsWithCanteenCheckout;
use Tests\Support\CanteenCheckoutTestSchema;
use Tests\TestCase;

class SaleVoidWalletRefundTest extends TestCase
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

    public function test_void_sale_refunds_wallet_cancels_settlement_and_voids_sale(): void
    {
        [$student] = $this->createEligibleStudent(walletBalance: 40.00);
        $product = $this->createProduct(price: 10.00);
        $cashier = $this->createCashier();

        $sale = app(PosCheckoutService::class)->checkout(
            $this->checkoutPayload((string) $student->id, $product, '2'),
            $cashier->id,
        );

        $this->assertSame(SaleStatus::COMPLETED, $sale->status);
        $this->assertSame('20.00', (string) UserWallet::query()->where('user_id', $student->id)->value('balance'));

        $voided = app(SaleVoidService::class)->void($sale, 'Cashier mistake', $cashier->id);

        $this->assertSame(SaleStatus::VOIDED, $voided->status);
        $this->assertNotNull($voided->voided_at);
        $this->assertSame('40.00', (string) UserWallet::query()->where('user_id', $student->id)->value('balance'));

        $settlement = WalletReadyTransaction::query()->where('sale_id', $sale->id)->first();
        $this->assertSame('cancelled', $settlement->status);

        $wallet = UserWallet::query()->where('user_id', $student->id)->first();
        $this->assertTrue(
            UserWalletTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->where('type', 'credit')
                ->exists()
        );
    }
}
