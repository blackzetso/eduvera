<?php

namespace App\Console\Commands;

use App\Services\DailyAbsenceCoverageService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CloseDailyCoverage extends Command
{
    protected $signature = 'timetable:close-daily-coverage {--date=}';

    protected $description = 'Archive today\'s temporary absence coverage and update teacher coverage balances';

    public function handle(DailyAbsenceCoverageService $service): int
    {
        $date = $this->option('date') ?? today()->toDateString();
        $report = $service->closeDay($date);

        $this->info("Closed daily coverage for {$date}");
        $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
