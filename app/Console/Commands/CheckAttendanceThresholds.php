<?php

namespace App\Console\Commands;

use App\Models\SystemTask;
use App\Services\AttendanceThresholdService;
use Illuminate\Console\Command;

class CheckAttendanceThresholds extends Command
{
    protected $signature = 'attendance:check-thresholds';

    protected $description = 'Evaluate student absence counts and create attendance alerts';

    public function handle(AttendanceThresholdService $service): int
    {
        $task = SystemTask::getTask('check_attendance_thresholds', 86400);
        $task->markAsRunning();

        $created = $service->checkAllStudents();

        $task->saveResult([
            'success' => true,
            'alerts_created' => $created,
            'ran_at' => now()->toIso8601String(),
        ]);

        $this->info("Attendance threshold check complete. Alerts processed: {$created}");

        return self::SUCCESS;
    }
}
