<?php

namespace Tests\Feature\Canteen\Concerns;

use App\Models\StudentEnrollment;
use App\Models\User;
use App\Models\UserWallet;
use App\Modules\Canteen\Integration\Adapters\UserWalletSettlementAdapter;
use App\Modules\Canteen\Integration\Contracts\WalletSettlementPort;
use App\Modules\Canteen\Models\InventoryTransaction;
use App\Modules\Canteen\Models\Product;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Models\Category;
use App\Modules\Canteen\Models\StudentProfile;
use App\Modules\Canteen\Services\InventoryLedgerService;
use App\Modules\Canteen\Support\SaleStatus;
use Illuminate\Support\Str;

trait InteractsWithCanteenCheckout
{
    protected function bindUserWalletAdapter(): void
    {
        $this->app->singleton(WalletSettlementPort::class, fn () => new UserWalletSettlementAdapter);
    }

    protected function createCashier(): User
    {
        return User::factory()->create([
            'user_type' => 'admin',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: StudentProfile}
     */
    protected function createEligibleStudent(array $overrides = [], float $walletBalance = 100.00): array
    {
        $student = User::factory()->create(array_merge([
            'user_type' => 'student',
            'student_status' => 'active',
            'student_code' => 'STU-'.Str::upper(Str::random(6)),
        ], $overrides));

        StudentEnrollment::query()->create([
            'student_id' => $student->id,
            'academic_year' => '2025-2026',
            'grade_name' => 'Grade 5',
            'class_name' => '5A',
            'enrollment_date' => now()->toDateString(),
            'status' => 'active',
            'is_current' => true,
        ]);

        UserWallet::query()->create([
            'user_id' => $student->id,
            'balance' => $walletBalance,
            'total_credited' => $walletBalance,
            'total_debited' => 0,
        ]);

        $profile = StudentProfile::query()->create([
            'user_id' => $student->id,
            'student_id_ref' => (string) $student->id,
            'student_name' => $student->name,
            'grade' => 'Grade 5',
            'class_name' => '5A',
            'is_active' => true,
        ]);

        return [$student, $profile];
    }

    protected function createProduct(float $price = 10.00, float $openingStock = 50.00): Product
    {
        $category = Category::query()->create([
            'name' => 'Snacks',
            'slug' => 'snacks-'.Str::lower(Str::random(4)),
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'SKU-'.Str::upper(Str::random(6)),
            'name' => 'Test Product',
            'unit' => 'piece',
            'selling_price' => $price,
            'is_active' => true,
        ]);

        app(InventoryLedgerService::class)->record(
            $product->id,
            'opening_stock',
            (string) $openingStock,
        );

        return $product;
    }

    /**
     * @return array<string, mixed>
     */
    protected function checkoutPayload(string $studentRef, Product $product, string $quantity = '1'): array
    {
        return [
            'student_id_ref' => $studentRef,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                ],
            ],
        ];
    }

    protected function inventoryOnHand(string $productId): string
    {
        return (string) InventoryTransaction::query()
            ->where('product_id', $productId)
            ->sum('quantity_delta');
    }

    protected function seedCompletedSale(User $student, string $total = '8.00'): Sale
    {
        $cashier = $this->createCashier();

        return Sale::query()->create([
            'sale_number' => 'CN-SEED-'.Str::upper(Str::random(6)),
            'student_id_ref' => (string) $student->id,
            'student_user_id' => $student->id,
            'student_name' => $student->name,
            'subtotal' => $total,
            'discount' => '0',
            'total' => $total,
            'payment_method' => 'wallet_ready',
            'status' => SaleStatus::COMPLETED,
            'daily_limit_checked' => true,
            'restrictions_checked' => true,
            'cashier_user_id' => $cashier->id,
            'sold_at' => now(),
            'completed_at' => now(),
        ]);
    }
}
