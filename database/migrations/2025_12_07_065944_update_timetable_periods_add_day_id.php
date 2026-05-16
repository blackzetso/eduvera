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
        // التحقق من وجود العمود قبل إضافته
        if (!Schema::hasColumn('timetable_periods', 'timetable_day_id')) {
            Schema::table('timetable_periods', function (Blueprint $table) {
                // إضافة timetable_day_id
                $table->foreignId('timetable_day_id')->nullable()->after('timetable_id')->constrained('timetable_days')->onDelete('cascade');
            });
        }
        
        // محاولة حذف unique constraint القديم إذا كان موجوداً (يستخدم raw SQL)
        try {
            $indexes = DB::select("SHOW INDEX FROM `timetable_periods` WHERE Key_name = 'unique_period'");
            if (count($indexes) > 0) {
                DB::statement('ALTER TABLE `timetable_periods` DROP INDEX `unique_period`');
            }
        } catch (\Exception $e) {
            // Ignore if constraint doesn't exist or can't be dropped
        }
        
        // حذف عمود day إذا كان موجوداً - استخدام raw SQL لتجنب مشاكل unique constraint
        try {
            if (Schema::hasColumn('timetable_periods', 'day')) {
                DB::statement('ALTER TABLE `timetable_periods` DROP COLUMN `day`');
            }
        } catch (\Exception $e) {
            // Ignore if column doesn't exist or can't be dropped
        }
        
        // إضافة unique constraint جديد إذا لم يكن موجوداً
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timetable_periods', function (Blueprint $table) {
            // إعادة إضافة day
            $table->enum('day', ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'])->after('timetable_id');
            
            // حذف unique constraint الجديد
            $table->dropUnique('unique_period_new');
        });
        
        Schema::table('timetable_periods', function (Blueprint $table) {
            // حذف timetable_day_id
            $table->dropForeign(['timetable_day_id']);
            $table->dropColumn('timetable_day_id');
            
            // إعادة unique constraint القديم
            $table->unique(['timetable_id', 'day', 'period_number', 'category_id'], 'unique_period');
        });
    }
};
