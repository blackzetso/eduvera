<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('status', 50);
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->enum('source', ['manual', 'excel', 'api', 'system'])->default('manual');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->unique(['teacher_id', 'attendance_date'], 'unique_teacher_attendance_per_day');
            $table->index(['attendance_date', 'status'], 'idx_teacher_att_date_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_attendances');
    }
};
