<?php

// Configurable absence/late thresholds per category or school-wide.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_thresholds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->string('academic_year')->nullable();
            $table->enum('period_type', ['term', 'year', 'custom'])->default('year');
            $table->unsignedInteger('warning_absences')->default(5);
            $table->unsignedInteger('critical_absences')->default(10);
            $table->unsignedInteger('warning_late_count')->nullable();
            $table->unsignedInteger('critical_late_count')->nullable();
            $table->boolean('auto_notify_guardian')->default(true);
            $table->boolean('suggest_block_at_critical')->default(true);
            $table->boolean('affects_grade_eligibility')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category_id', 'academic_year'], 'idx_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_thresholds');
    }
};
