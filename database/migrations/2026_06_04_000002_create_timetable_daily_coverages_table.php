<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_daily_coverages', function (Blueprint $table) {
            $table->id();
            $table->date('coverage_date');
            $table->foreignId('timetable_id')->constrained('timetables')->cascadeOnDelete();
            $table->foreignId('timetable_period_id')->constrained('timetable_periods')->cascadeOnDelete();
            $table->foreignId('absent_teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('replacement_teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->unsignedTinyInteger('match_score')->default(0);
            $table->json('match_reasons')->nullable();
            $table->enum('status', ['draft', 'approved', 'closed', 'cancelled'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['coverage_date', 'timetable_period_id'],
                'unique_coverage_per_period_per_day'
            );
            $table->index(['coverage_date', 'status'], 'idx_coverage_date_status');
            $table->index(['replacement_teacher_id', 'coverage_date'], 'idx_replacement_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_daily_coverages');
    }
};
