<?php

namespace Tests\Unit\Canteen;

use App\Modules\Canteen\Models\Category;
use App\Modules\Canteen\Models\Product;
use App\Modules\Canteen\Models\StudentProfile;
use App\Modules\Canteen\Services\CanteenHealthRestrictionService;
use Illuminate\Support\Str;
use Tests\Support\CanteenGuardianTestSchema;
use Tests\TestCase;

class CanteenHealthRestrictionServiceTest extends TestCase
{
    use CanteenGuardianTestSchema;

    protected CanteenHealthRestrictionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCanteenGuardianTestSchema();
        $this->service = new CanteenHealthRestrictionService;
    }

    public function test_block_reason_when_guardian_purchase_blocked(): void
    {
        $profile = StudentProfile::query()->create([
            'student_id_ref' => '1',
            'student_name' => 'Test',
            'metadata' => ['guardian_purchase_blocked' => true],
            'is_active' => true,
        ]);

        $reason = $this->service->blockReasonForStudent($profile);

        $this->assertStringContainsString('Guardian has blocked', $reason);
    }

    public function test_evaluate_cart_blocks_products_with_matching_health_tags(): void
    {
        $profile = StudentProfile::query()->create([
            'student_id_ref' => '1',
            'student_name' => 'Test',
            'health_restrictions' => [
                'allergies' => ['peanut'],
                'blocked_tags' => ['soda'],
            ],
            'is_active' => true,
        ]);

        $product = $this->createTaggedProduct(['peanut', 'snack']);

        $result = $this->service->evaluateCart($profile, [
            ['product_id' => $product->id, 'quantity' => '1'],
        ]);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('peanut', $result['blocks'][0]['message']);
    }

    protected function createTaggedProduct(array $tags): Product
    {
        $category = Category::query()->create([
            'name' => 'Food',
            'slug' => 'food-'.Str::lower(Str::random(4)),
            'is_active' => true,
        ]);

        return Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'SKU-'.Str::upper(Str::random(5)),
            'name' => 'Tagged Product',
            'selling_price' => '5.00',
            'restriction_tags' => $tags,
            'is_active' => true,
        ]);
    }
}
