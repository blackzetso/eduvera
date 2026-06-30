<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timetable_id')->constrained('timetables')->cascadeOnDelete();
            $table->string('name');
            $table->string('department_label');
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['timetable_id', 'status']);
        });

        Schema::create('department_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_plan_id')->constrained('department_plans')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->unsignedSmallInteger('required_periods')->default(0);
            $table->timestamps();

            $table->unique(
                ['department_plan_id', 'subject_id', 'category_id'],
                'uniq_dept_plan_item'
            );
        });

        Schema::create('department_plan_staffing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_plan_id')->constrained('department_plans')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->unsignedSmallInteger('allocated_periods')->default(0);
            $table->timestamps();

            $table->index(['department_plan_id', 'subject_id'], 'idx_dept_staff_subject');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_plan_staffing');
        Schema::dropIfExists('department_plan_items');
        Schema::dropIfExists('department_plans');
    }
};
