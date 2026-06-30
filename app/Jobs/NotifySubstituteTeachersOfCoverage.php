<?php

namespace App\Jobs;

use App\Services\DailyAbsenceCoverageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifySubstituteTeachersOfCoverage implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array<string, mixed>>  $assignmentRows
     */
    public function __construct(
        public string $date,
        public array $assignmentRows,
        public string $status = 'approved',
    ) {}

    public function handle(DailyAbsenceCoverageService $service): void
    {
        $service->sendSubstituteTeacherNotifications($this->date, $this->assignmentRows, $this->status);
    }
}
