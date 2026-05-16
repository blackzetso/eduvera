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
        // إزالة الـ constraint القديم 'unique_period' إذا كان موجوداً
        try {
            $indexes = DB::select("SHOW INDEX FROM `timetable_periods` WHERE Key_name = 'unique_period'");
            if (count($indexes) > 0) {
                DB::statement('ALTER TABLE `timetable_periods` DROP INDEX `unique_period`');
            }
        } catch (\Exception $e) {
            // Ignore if constraint doesn't exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // لا حاجة لإعادة الـ constraint القديم لأنه تم استبداله بـ unique_period_time
    }
};
