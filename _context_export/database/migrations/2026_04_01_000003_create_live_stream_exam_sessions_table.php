<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('live_stream_exam_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_stream_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('time_limit')->default(600); // seconds
            $table->enum('status', ['active', 'closed'])->default('active');
            $table->timestamp('activated_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_stream_exam_sessions');
    }
};
