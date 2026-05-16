<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_stream_quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_stream_id')->constrained()->cascadeOnDelete();
            $table->text('question_text');
            $table->enum('question_type', [
                'true_false',
                'true_false_correction',
                'fill_blank',
                'multiple_choice',
                'essay',
                'pdf_exam',
            ])->default('true_false');
            $table->json('options')->nullable();           // for multiple_choice
            $table->string('correct_answer')->nullable();  // for true_false, multiple_choice
            $table->boolean('allow_multiple')->default(false); // multiple answers allowed
            $table->smallInteger('time_limit')->default(60);   // seconds
            $table->enum('status', ['draft', 'active', 'closed'])->default('draft');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('attachment_path')->nullable();  // PDF path on public disk
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_stream_quizzes');
    }
};
