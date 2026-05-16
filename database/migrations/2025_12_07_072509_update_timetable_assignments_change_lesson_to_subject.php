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
        // التحقق من وجود العمود lesson_id
        if (Schema::hasColumn('timetable_assignments', 'lesson_id')) {
            Schema::table('timetable_assignments', function (Blueprint $table) {
                // حذف foreign key constraint القديم
                try {
                    $table->dropForeign(['lesson_id']);
                } catch (\Exception $e) {
                    // Ignore if foreign key doesn't exist
                }
            });
            
            // تغيير اسم العمود من lesson_id إلى subject_id
            DB::statement('ALTER TABLE `timetable_assignments` CHANGE `lesson_id` `subject_id` BIGINT UNSIGNED NULL');
            
            // إضافة foreign key constraint جديد
            Schema::table('timetable_assignments', function (Blueprint $table) {
                $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('timetable_assignments', 'subject_id')) {
            Schema::table('timetable_assignments', function (Blueprint $table) {
                // حذف foreign key constraint الجديد
                try {
                    $table->dropForeign(['subject_id']);
                } catch (\Exception $e) {
                    // Ignore if foreign key doesn't exist
                }
            });
            
            // إعادة تغيير اسم العمود من subject_id إلى lesson_id
            DB::statement('ALTER TABLE `timetable_assignments` CHANGE `subject_id` `lesson_id` BIGINT UNSIGNED NULL');
            
            // إعادة إضافة foreign key constraint القديم
            Schema::table('timetable_assignments', function (Blueprint $table) {
                $table->foreign('lesson_id')->references('id')->on('lessons')->onDelete('cascade');
            });
        }
    }
};
