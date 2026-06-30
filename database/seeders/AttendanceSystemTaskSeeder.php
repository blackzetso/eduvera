<?php

namespace Database\Seeders;

use App\Models\SystemTask;
use Illuminate\Database\Seeder;

class AttendanceSystemTaskSeeder extends Seeder
{
    public function run(): void
    {
        SystemTask::firstOrCreate(
            ['task_name' => 'check_attendance_thresholds'],
            [
                'next_run_at' => now()->addDay()->setTime(2, 0),
                'run_interval' => 86400,
                'is_enabled' => true,
            ]
        );

        SystemTask::firstOrCreate(
            ['task_name' => 'sync_live_stream_attendances'],
            [
                'next_run_at' => now()->addHour(),
                'run_interval' => 3600,
                'is_enabled' => true,
            ]
        );
    }
}
