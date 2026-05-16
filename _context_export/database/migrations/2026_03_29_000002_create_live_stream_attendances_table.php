<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_stream_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_stream_id')->constrained('live_streams')->onDelete('cascade');
            $table->string('student_name');
            $table->string('student_email')->nullable();
            $table->dateTime('join_time')->nullable();
            $table->dateTime('leave_time')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_stream_attendances');
    }
};
