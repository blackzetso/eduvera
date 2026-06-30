<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lecture_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('lesson_enrollments')->cascadeOnDelete();
            $table->foreignId('lecture_id')->constrained('lectures')->cascadeOnDelete();
            $table->timestamp('first_viewed_at')->useCurrent();
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamps();

            $table->unique(['enrollment_id', 'lecture_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecture_views');
    }
};
