<?php

namespace Tests\Unit\Canteen;

use App\Models\User;
use App\Modules\Canteen\Integration\Adapters\CoreGuardianIntegrationAdapter;
use App\Modules\Canteen\Integration\Adapters\EduveraFinanceIntegrationAdapter;
use App\Modules\Canteen\Models\CanteenFinanceEntry;
use App\Modules\Canteen\Models\InventoryTransaction;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Models\WalletReadyTransaction;
use App\Modules\Canteen\Services\CanteenSettingsService;
use App\Modules\Canteen\Support\SaleStatus;
use Illuminate\Support\Str;
use Tests\Feature\Canteen\Concerns\InteractsWithCanteenCheckout;
use Tests\Support\CanteenCheckoutTestSchema;
use Tests\Support\CanteenPhase8TestSchema;
use Tests\TestCase;

class EduveraFinanceIntegrationAdapterTest extends TestCase
{
    use CanteenCheckoutTestSchema;
    use CanteenPhase8TestSchema;
    use InteractsWithCanteenCheckout;

    protected EduveraFinanceIntegrationAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        config(['canteen.enabled' => true]);
        $this->setUpCanteenCheckoutTestSchema();
        $this->extendCanteenPhase8TestSchema();

        $this->app->singleton(\App\Modules\Canteen\Integration\Contracts\GuardianIntegrationPort::class, CoreGuardianIntegrationAdapter::class);
        $this->adapter = new EduveraFinanceIntegrationAdapter(
            new CoreGuardianIntegrationAdapter,
            app(CanteenSettingsService::class),
        );
    }

    public function test_record_sale_completed_creates_student_and_family_entries(): void
    {
        [$student, $guardian] = $this->seedFamily();
        $cashier = $this->createCashier();
        $sale = $this->createSale($student, $guardian, $cashier);

        InventoryTransaction::query()->create([
            'product_id' => $this->createProduct()->id,
            'type' => 'sale',
            'quantity_delta' => '-1.000',
            'reference_type' => 'sale',
            'reference_id' => $sale->id,
            'occurred_at' => now(),
        ]);

        $this->adapter->recordSaleCompleted($sale->fresh(['items', 'walletReadyTransaction']));

        $this->assertSame(2, CanteenFinanceEntry::query()->where('sale_id', $sale->id)->count());
        $this->assertNotNull(CanteenFinanceEntry::query()
            ->where('sale_id', $sale->id)
            ->where('ledger_scope', CanteenFinanceEntry::SCOPE_STUDENT)
            ->value('wallet_tx_id'));
    }

    public function test_record_sale_voided_reverses_purchase_and_creates_credit_entries(): void
    {
        [$student, $guardian] = $this->seedFamily();
        $cashier = $this->createCashier();
        $sale = $this->createSale($student, $guardian, $cashier);

        $this->adapter->recordSaleCompleted($sale->fresh(['items', 'walletReadyTransaction']));
        $sale->update(['status' => SaleStatus::VOIDED, 'voided_at' => now(), 'void_reason' => 'Test']);

        $this->adapter->recordSaleVoided($sale->fresh(['items', 'walletReadyTransaction']));

        $this->assertSame(
            CanteenFinanceEntry::STATUS_REVERSED,
            CanteenFinanceEntry::query()
                ->where('sale_id', $sale->id)
                ->where('entry_type', CanteenFinanceEntry::TYPE_PURCHASE)
                ->value('status'),
        );
        $this->assertTrue(CanteenFinanceEntry::query()
            ->where('sale_id', $sale->id)
            ->where('entry_type', CanteenFinanceEntry::TYPE_VOID)
            ->exists());
    }

    /**
     * @return array{0: User, 1: User}
     */
    protected function seedFamily(): array
    {
        $student = User::factory()->create(['user_type' => 'student']);
        $guardian = User::factory()->create(['user_type' => 'guardian']);

        $student->guardians()->attach($guardian->id, [
            'relationship_type' => 'guardian',
            'is_primary' => true,
            'is_financial_responsible' => true,
        ]);

        return [$student, $guardian];
    }

    protected function createSale(User $student, User $guardian, User $cashier): Sale
    {
        $sale = Sale::query()->create([
            'sale_number' => 'CN-'.Str::upper(Str::random(6)),
            'student_id_ref' => (string) $student->id,
            'student_user_id' => $student->id,
            'primary_guardian_user_id' => $guardian->id,
            'student_name' => $student->name,
            'subtotal' => '12.00',
            'discount' => '0',
            'total' => '12.00',
            'payment_method' => 'wallet_ready',
            'status' => SaleStatus::COMPLETED,
            'cashier_user_id' => $cashier->id,
            'sold_at' => now(),
            'completed_at' => now(),
        ]);

        WalletReadyTransaction::query()->create([
            'sale_id' => $sale->id,
            'student_id_ref' => (string) $student->id,
            'amount' => '12.00',
            'status' => 'posted',
            'external_wallet_tx_id' => 99,
            'idempotency_key' => 'idem-'.Str::uuid(),
            'posted_at' => now(),
        ]);

        return $sale;
    }
}
