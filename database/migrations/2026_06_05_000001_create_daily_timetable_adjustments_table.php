<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_timetable_adjustments', function (Blueprint $table) {
            $table->id();
            $table->date('adjustment_date');
            $table->foreignId('timetable_id')->constrained('timetables')->cascadeOnDelete();
            $table->enum('swap_type', ['move_lesson', 'swap_lessons', 'replace_teacher']);
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('timetable_period_id')->constrained('timetable_periods')->cascadeOnDelete();
            $table->unsignedBigInteger('target_timetable_period_id')->nullable();
            $table->unsignedBigInteger('secondary_teacher_id')->nullable();
            $table->unsignedBigInteger('secondary_timetable_period_id')->nullable();
            $table->unsignedBigInteger('replacement_teacher_id')->nullable();
            $table->unsignedBigInteger('trigger_period_id')->nullable();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->unsignedTinyInteger('original_period_number')->nullable();
            $table->unsignedTinyInteger('new_period_number')->nullable();
            $table->string('reason')->nullable();
            $table->json('impact_preview')->nullable();
            $table->enum('status', ['draft', 'approved', 'closed', 'cancelled'])->default('approved');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['adjustment_date', 'status'], 'idx_adj_date_status');
            $table->index(['teacher_id', 'adjustment_date'], 'idx_adj_teacher_date');

            $table->foreign('target_timetable_period_id', 'fk_adj_target_period')
                ->references('id')->on('timetable_periods')->nullOnDelete();
            $table->foreign('secondary_teacher_id', 'fk_adj_sec_teacher')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('secondary_timetable_period_id', 'fk_adj_sec_period')
                ->references('id')->on('timetable_periods')->nullOnDelete();
            $table->foreign('replacement_teacher_id', 'fk_adj_replace_teacher')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('trigger_period_id', 'fk_adj_trigger_period')
                ->references('id')->on('timetable_periods')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_timetable_adjustments');
    }
};
