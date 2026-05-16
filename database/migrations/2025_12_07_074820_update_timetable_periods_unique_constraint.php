<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // حذف الـ unique constraint القديم الذي يعتمد على period_number
        try {
            $indexes = DB::select("SHOW INDEX FROM `timetable_periods` WHERE Key_name = 'unique_period_new'");
            if (count($indexes) > 0) {
                DB::statement('ALTER TABLE `timetable_periods` DROP INDEX `unique_period_new`');
            }
        } catch (\Exception $e) {
            // Ignore if constraint doesn't exist
        }
        
        // إضافة unique constraint جديد يعتمد على الوقت بدلاً من period_number
        // هذا يسمح بإضافة حصص متعددة في نفس اليوم والمرحلة، طالما أن الأوقات مختلفة
        try {
            $indexes = DB::select("SHOW INDEX FROM `timetable_periods` WHERE Key_name = 'unique_period_time'");
            if (count($indexes) == 0) {
                Schema::table('timetable_periods', function (Blueprint $table) {
                    $table->unique(['timetable_id', 'timetable_day_id', 'time_from', 'time_to', 'category_id'], 'unique_period_time');
                });
            }
        } catch (\Exception $e) {
            // Ignore if constraint already exists
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // حذف الـ unique constraint الجديد
        try {
            $indexes = DB::select("SHOW INDEX FROM `timetable_periods` WHERE Key_name = 'unique_period_time'");
            if (count($indexes) > 0) {
                DB::statement('ALTER TABLE `timetable_periods` DROP INDEX `unique_period_time`');
            }
        } catch (\Exception $e) {
            // Ignore if constraint doesn't exist
        }
        
        // إعادة الـ unique constraint القديم
        try {
            $indexes = DB::select("SHOW INDEX FROM `timetable_periods` WHERE Key_name = 'unique_period_new'");
            if (count($indexes) == 0) {
                Schema::table('timetable_periods', function (Blueprint $table) {
                    $table->unique(['timetable_id', 'timetable_day_id', 'period_number', 'category_id'], 'unique_period_new');
                });
            }
        } catch (\Exception $e) {
            // Ignore if constraint already exists
        }
    }
};
