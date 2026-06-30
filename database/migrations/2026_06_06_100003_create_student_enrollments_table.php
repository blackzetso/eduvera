<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('academic_year', 16);
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('stage_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('stage_name')->nullable();
            $table->string('grade_name')->nullable();
            $table->string('class_name')->nullable();
            $table->date('enrollment_date');
            $table->date('promotion_date')->nullable();
            $table->date('withdrawal_date')->nullable();
            $table->string('status', 32)->default('active');
            $table->string('action_type', 32)->default('initial');
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_current')->default(false);
            $table->string('source', 64)->nullable();
            $table->unsignedBigInteger('admission_reference_id')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'is_current']);
            $table->index(['academic_year', 'stage_category_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_enrollments');
    }
};
