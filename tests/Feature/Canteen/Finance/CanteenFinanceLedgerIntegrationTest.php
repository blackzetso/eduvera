<?php

namespace Tests\Feature\Canteen\Finance;

use App\Modules\Canteen\Events\CanteenSaleCompleted;
use App\Modules\Canteen\Integration\Adapters\EduveraFinanceIntegrationAdapter;
use App\Modules\Canteen\Integration\Contracts\FinanceIntegrationPort;
use App\Modules\Canteen\Models\CanteenFinanceEntry;
use App\Modules\Canteen\Services\CanteenFinanceReconciliationService;
use Tests\Feature\Canteen\Concerns\InteractsWithCanteenCheckout;
use Tests\Support\CanteenCheckoutTestSchema;
use Tests\Support\CanteenPhase8TestSchema;
use Tests\TestCase;

class CanteenFinanceLedgerIntegrationTest extends TestCase
{
    use CanteenCheckoutTestSchema;
    use CanteenPhase8TestSchema;
    use InteractsWithCanteenCheckout;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'canteen.enabled' => true,
            'canteen.integration.finance_adapter' => 'eduvera',
        ]);

        $this->setUpCanteenCheckoutTestSchema();
        $this->extendCanteenPhase8TestSchema();
        $this->app->singleton(FinanceIntegrationPort::class, EduveraFinanceIntegrationAdapter::class);
    }

    public function test_noop_adapter_writes_no_finance_rows(): void
    {
        $this->app->singleton(FinanceIntegrationPort::class, \App\Modules\Canteen\Integration\Adapters\NoopFinanceIntegrationAdapter::class);

        [$student] = $this->createEligibleStudent();
        $sale = $this->seedCompletedSale($student);

        app(FinanceIntegrationPort::class)->recordSaleCompleted($sale);

        $this->assertSame(0, CanteenFinanceEntry::query()->count());
    }

    public function test_completed_event_records_student_finance_entry_via_listener(): void
    {
        [$student] = $this->createEligibleStudent();
        $sale = $this->seedCompletedSale($student);

        event(new CanteenSaleCompleted($sale->fresh()));

        $this->assertTrue(CanteenFinanceEntry::query()
            ->where('sale_id', $sale->id)
            ->where('ledger_scope', CanteenFinanceEntry::SCOPE_STUDENT)
            ->exists());
    }

    public function test_reconciliation_marks_completed_sale_without_finance_as_missing(): void
    {
        [$student] = $this->createEligibleStudent();
        $sale = $this->seedCompletedSale($student);

        $row = app(CanteenFinanceReconciliationService::class)->reconcileSale($sale);

        $this->assertSame('finance_missing', $row['status']);
    }
}
