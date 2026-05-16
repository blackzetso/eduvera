<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_stream_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_stream_id')->constrained('live_streams')->onDelete('cascade');
            $table->string('reviewer_name');
            $table->string('reviewer_email')->nullable();
            $table->unsignedTinyInteger('rating')->default(5); // 1–5
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_stream_reviews');
    }
};
