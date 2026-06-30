<?php

namespace Tests\Unit\Canteen;

use App\Modules\Canteen\Models\CanteenFinanceEntry;
use App\Modules\Canteen\Services\CanteenFinanceReconciliationService;
use Tests\Feature\Canteen\Concerns\InteractsWithCanteenCheckout;
use Tests\Support\CanteenCheckoutTestSchema;
use Tests\Support\CanteenPhase8TestSchema;
use Tests\TestCase;

class CanteenFinanceReconciliationServiceTest extends TestCase
{
    use CanteenCheckoutTestSchema;
    use CanteenPhase8TestSchema;
    use InteractsWithCanteenCheckout;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCanteenCheckoutTestSchema();
        $this->extendCanteenPhase8TestSchema();
    }

    public function test_reconcile_sale_returns_matched_when_finance_and_inventory_exist(): void
    {
        [$student] = $this->createEligibleStudent();
        $product = $this->createProduct();
        $sale = $this->seedCompletedSale($student, '10.00');

        CanteenFinanceEntry::query()->create([
            'sale_id' => $sale->id,
            'entry_type' => CanteenFinanceEntry::TYPE_PURCHASE,
            'ledger_scope' => CanteenFinanceEntry::SCOPE_STUDENT,
            'student_user_id' => $student->id,
            'student_id_ref' => (string) $student->id,
            'amount' => '10.00',
            'direction' => CanteenFinanceEntry::DIRECTION_DEBIT,
            'status' => CanteenFinanceEntry::STATUS_POSTED,
            'posted_at' => now(),
        ]);

        app(\App\Modules\Canteen\Services\InventoryLedgerService::class)->recordSale($product->id, '1', $sale->id);

        $row = app(CanteenFinanceReconciliationService::class)->reconcileSale($sale->fresh());

        $this->assertSame('matched', $row['status']);
    }
}
