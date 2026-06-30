<?php

namespace Database\Seeders;

use App\Models\AttendanceThreshold;
use Illuminate\Database\Seeder;

class AttendanceThresholdSeeder extends Seeder
{
    public function run(): void
    {
        AttendanceThreshold::firstOrCreate(
            ['category_id' => null, 'academic_year' => null],
            [
                'period_type' => 'year',
                'warning_absences' => 5,
                'critical_absences' => 10,
                'auto_notify_guardian' => true,
                'suggest_block_at_critical' => true,
                'is_active' => true,
            ]
        );
    }
}
