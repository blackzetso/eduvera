<?php

namespace Tests\Feature\Canteen\Guardian;

use App\Modules\Canteen\Integration\Adapters\CoreGuardianIntegrationAdapter;
use App\Modules\Canteen\Integration\Contracts\GuardianIntegrationPort;
use App\Modules\Canteen\Models\StudentBlockedProduct;
use Tests\Feature\Canteen\Guardian\Concerns\InteractsWithGuardianCanteenApi;
use Tests\Support\CanteenGuardianTestSchema;
use Tests\TestCase;

class GuardianStudentLimitsApiTest extends TestCase
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

    public function test_guardian_can_update_daily_limit_and_purchase_block(): void
    {
        [$student, $guardian] = $this->seedLinkedFamily();

        $this->actingAs($guardian)
            ->putJson($this->guardianApiUrl("children/{$student->id}/daily-limit"), [
                'daily_spending_limit' => 30,
            ])
            ->assertOk()
            ->assertJsonPath('daily_limit.limit', '30.00');

        $this->actingAs($guardian)
            ->putJson($this->guardianApiUrl("children/{$student->id}/purchase-blocked"), [
                'blocked' => true,
            ])
            ->assertOk()
            ->assertJsonPath('guardian_purchase_blocked', true);
    }

    public function test_guardian_can_manage_health_restrictions(): void
    {
        [$student, $guardian] = $this->seedLinkedFamily();

        $this->actingAs($guardian)
            ->putJson($this->guardianApiUrl("children/{$student->id}/health-restrictions"), [
                'allergies' => ['peanut'],
                'blocked_tags' => ['soda'],
                'block_all_purchases' => false,
                'notes' => 'Avoid peanuts.',
            ])
            ->assertOk()
            ->assertJsonPath('health_restrictions.allergies.0', 'peanut')
            ->assertJsonPath('health_restrictions.notes', 'Avoid peanuts.');
    }

    public function test_guardian_can_block_and_unblock_products_and_categories(): void
    {
        [$student, $guardian] = $this->seedLinkedFamily();
        [$category, $product] = $this->seedProduct();

        $this->actingAs($guardian)
            ->postJson($this->guardianApiUrl("children/{$student->id}/blocked-products"), [
                'product_id' => $product->id,
                'restriction_type' => 'permanent',
                'reason' => 'Too sugary',
            ])
            ->assertCreated();

        $this->actingAs($guardian)
            ->postJson($this->guardianApiUrl("children/{$student->id}/blocked-categories"), [
                'category_id' => $category->id,
                'restriction_type' => 'permanent',
                'reason' => 'No snacks',
            ])
            ->assertCreated();

        $this->actingAs($guardian)
            ->getJson($this->guardianApiUrl("children/{$student->id}/blocks"))
            ->assertOk()
            ->assertJsonCount(1, 'blocked_products')
            ->assertJsonCount(1, 'blocked_categories');

        $block = StudentBlockedProduct::query()->firstOrFail();

        $this->actingAs($guardian)
            ->deleteJson($this->guardianApiUrl("children/{$student->id}/blocked-products/{$block->id}"))
            ->assertOk()
            ->assertJsonPath('message', 'Product block removed.');
    }
}
