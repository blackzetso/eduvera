<?php

namespace Tests\Feature\Canteen\Guardian\Concerns;

use App\Models\User;
use App\Models\UserWallet;
use App\Modules\Canteen\Models\Category;
use App\Modules\Canteen\Models\Product;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Models\SaleItem;
use App\Modules\Canteen\Models\StudentProfile;
use App\Modules\Canteen\Models\WalletReadyTransaction;
use App\Modules\Canteen\Support\SaleStatus;
use Illuminate\Support\Str;

trait InteractsWithGuardianCanteenApi
{
    /**
     * @return array{0: User, 1: User}
     */
    protected function seedLinkedFamily(array $studentOverrides = [], array $guardianOverrides = []): array
    {
        $student = User::factory()->create(array_merge([
            'user_type' => 'student',
            'student_status' => 'active',
            'student_code' => 'STU-'.Str::upper(Str::random(6)),
        ], $studentOverrides));

        $guardian = User::factory()->create(array_merge([
            'user_type' => 'guardian',
        ], $guardianOverrides));

        $student->guardians()->attach($guardian->id, [
            'relationship_type' => 'guardian',
            'is_primary' => true,
            'is_financial_responsible' => true,
        ]);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'primary_guardian_user_id' => $guardian->id,
            'guardian_id_ref' => (string) $guardian->id,
            'student_id_ref' => (string) $student->id,
            'student_name' => $student->name,
            'daily_spending_limit' => 25.00,
            'is_active' => true,
        ]);

        return [$student, $guardian];
    }

    protected function seedStudentWallet(User $student, float $balance = 40.00): void
    {
        UserWallet::query()->create([
            'user_id' => $student->id,
            'balance' => $balance,
            'total_credited' => $balance,
            'total_debited' => 0,
        ]);
    }

    /**
     * @return array{0: Category, 1: Product}
     */
    protected function seedProduct(): array
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
            'selling_price' => 10.00,
            'is_active' => true,
        ]);

        return [$category, $product];
    }

    protected function seedCompletedSale(
        User $student,
        User $cashier,
        float $total = 12.50,
        ?\Illuminate\Support\Carbon $soldAt = null,
    ): Sale {
        $soldAt ??= now();

        $sale = Sale::query()->create([
            'sale_number' => 'SALE-'.Str::upper(Str::random(8)),
            'student_id_ref' => (string) $student->id,
            'student_user_id' => $student->id,
            'student_name' => $student->name,
            'subtotal' => $total,
            'discount' => 0,
            'total' => $total,
            'payment_method' => 'wallet_ready',
            'status' => SaleStatus::COMPLETED,
            'cashier_user_id' => $cashier->id,
            'sold_at' => $soldAt,
            'completed_at' => $soldAt,
        ]);

        return $sale;
    }

    protected function seedSaleWithItem(Sale $sale, Product $product, string $quantity = '1'): SaleItem
    {
        $lineTotal = bcmul((string) $product->selling_price, $quantity, 2);

        return SaleItem::query()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => $product->selling_price,
            'quantity' => $quantity,
            'line_total' => $lineTotal,
        ]);
    }

    protected function seedWalletSettlement(Sale $sale, string $status = 'posted', ?int $externalTxId = null): void
    {
        WalletReadyTransaction::query()->create([
            'sale_id' => $sale->id,
            'student_id_ref' => $sale->student_id_ref,
            'transaction_type' => 'debit',
            'amount' => $sale->total,
            'status' => $status,
            'external_wallet_tx_id' => $externalTxId,
            'idempotency_key' => 'idem-'.Str::uuid(),
        ]);
    }

    protected function guardianApiUrl(string $path = ''): string
    {
        return '/guardian/canteen/api'.($path !== '' ? '/'.ltrim($path, '/') : '');
    }
}
