<?php

namespace Tests\Feature\Canteen\Guardian;

use App\Models\User;
use App\Modules\Canteen\Integration\Adapters\CoreGuardianIntegrationAdapter;
use App\Modules\Canteen\Integration\Contracts\GuardianIntegrationPort;
use Tests\Feature\Canteen\Guardian\Concerns\InteractsWithGuardianCanteenApi;
use Tests\Support\CanteenGuardianTestSchema;
use Tests\TestCase;

class GuardianCanteenDashboardApiTest extends TestCase
{
    use CanteenGuardianTestSchema;
    use InteractsWithGuardianCanteenApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCanteenGuardianTestSchema();
        config(['canteen.enabled' => true]);
        $this->app->singleton(GuardianIntegrationPort::class, CoreGuardianIntegrationAdapter::class);
    }

    public function test_summary_lists_linked_children(): void
    {
        [$student, $guardian] = $this->seedLinkedFamily();
        $this->seedStudentWallet($student, 35.50);
        config(['canteen.integration.wallet_adapter' => 'user_wallet']);

        $this->actingAs($guardian)
            ->getJson($this->guardianApiUrl('summary'))
            ->assertOk()
            ->assertJsonPath('guardian_id', $guardian->id)
            ->assertJsonPath('children_count', 1)
            ->assertJsonPath('wallet_adapter', 'user_wallet')
            ->assertJsonPath('children.0.student_id', $student->id)
            ->assertJsonPath('children.0.wallet.balance', 35.5)
            ->assertJsonPath('children.0.wallet.status', 'active');
    }

    public function test_summary_uses_pending_wallet_mode_when_adapter_is_pending(): void
    {
        [$student, $guardian] = $this->seedLinkedFamily();
        $this->seedStudentWallet($student, 35.50);
        config(['canteen.integration.wallet_adapter' => 'pending']);

        $this->actingAs($guardian)
            ->getJson($this->guardianApiUrl('summary'))
            ->assertOk()
            ->assertJsonPath('wallet_adapter', 'pending')
            ->assertJsonPath('children.0.wallet.balance', null)
            ->assertJsonPath('children.0.wallet.status', 'queued');
    }

    public function test_purchases_and_spending_endpoints_return_student_sales(): void
    {
        [$student, $guardian] = $this->seedLinkedFamily();
        $cashier = User::factory()->create(['user_type' => 'admin']);
        [, $product] = $this->seedProduct();

        $sale = $this->seedCompletedSale($student, $cashier, 15.00, now()->subDay());
        $this->seedSaleWithItem($sale, $product);
        $this->seedWalletSettlement($sale, 'posted', 101);

        config(['canteen.integration.wallet_adapter' => 'user_wallet']);

        $this->actingAs($guardian)
            ->getJson($this->guardianApiUrl("children/{$student->id}/purchases"))
            ->assertOk()
            ->assertJsonPath('student_id', $student->id)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $sale->id)
            ->assertJsonPath('data.0.total', '15.00');

        $this->actingAs($guardian)
            ->getJson($this->guardianApiUrl("children/{$student->id}/purchases/{$sale->id}"))
            ->assertOk()
            ->assertJsonPath('sale.id', $sale->id)
            ->assertJsonPath('wallet_settlement.adapter', 'user_wallet')
            ->assertJsonPath('wallet_settlement.status', 'posted')
            ->assertJsonPath('wallet_settlement.external_wallet_tx_id', 101)
            ->assertJsonCount(1, 'sale.items');

        $this->actingAs($guardian)
            ->getJson($this->guardianApiUrl("children/{$student->id}/spending"))
            ->assertOk()
            ->assertJsonPath('student_id', $student->id)
            ->assertJsonPath('transaction_count', 1)
            ->assertJsonPath('total', '15');
    }

    public function test_purchase_detail_returns_404_for_other_students_sale(): void
    {
        [$student, $guardian] = $this->seedLinkedFamily();
        $otherStudent = User::factory()->create(['user_type' => 'student']);
        $cashier = User::factory()->create(['user_type' => 'admin']);
        $otherSale = $this->seedCompletedSale($otherStudent, $cashier);

        $this->actingAs($guardian)
            ->getJson($this->guardianApiUrl("children/{$student->id}/purchases/{$otherSale->id}"))
            ->assertNotFound();
    }
}
