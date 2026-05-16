<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_stream_quiz_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_stream_quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('live_stream_id')->constrained()->cascadeOnDelete();
            $table->string('student_name');
            $table->string('student_identifier'); // UUID from localStorage
            $table->text('answer');               // "true"/"false" / option index / JSON array / free text
            $table->text('correction')->nullable(); // for true_false_correction type
            $table->boolean('is_correct')->nullable(); // auto-calculated for tf and mc
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();

            $table->unique(['live_stream_quiz_id', 'student_identifier'], 'quiz_student_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_stream_quiz_answers');
    }
};
