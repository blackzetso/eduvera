<?php

namespace App\Modules\Canteen\Console\Commands;

use App\Modules\Canteen\Services\CanteenFinanceReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ReconcileCanteenFinanceCommand extends Command
{
    protected $signature = 'canteen:reconcile-finance
                            {--from= : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d)}';

    protected $description = 'Reconcile canteen sales against wallet settlements, inventory, and finance ledger entries';

    public function handle(CanteenFinanceReconciliationService $reconciliation): int
    {
        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))
            : today();

        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))
            : $to->copy()->subDays(6);

        $report = $reconciliation->reconcile($from, $to);

        $this->info("Reconciliation {$report['from']} to {$report['to']}");

        foreach ($report['summary'] as $status => $count) {
            $this->line(sprintf('  %-20s %d', $status.':', $count));
        }

        $mismatches = collect($report['rows'])->where('status', '!=', 'matched');

        foreach ($mismatches as $row) {
            $this->warn("{$row['sale_number']} [{$row['status']}] total={$row['sale_total']}");
        }

        return self::SUCCESS;
    }
}
