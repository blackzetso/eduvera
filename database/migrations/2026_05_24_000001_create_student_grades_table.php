<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->string('term_label', 100)->nullable();
            $table->enum('assessment_type', ['exam', 'quiz', 'assignment', 'monthly'])->default('exam');
            $table->string('title');
            $table->decimal('score', 8, 2);
            $table->decimal('max_score', 8, 2)->default(100);
            $table->date('assessed_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'assessed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_grades');
    }
};
