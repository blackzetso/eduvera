<?php

// Auto-generated attendance warning/critical alerts.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('academic_year')->nullable();
            $table->string('period_label', 100)->nullable();
            $table->enum('level', ['warning', 'critical']);
            $table->unsignedInteger('absences_count');
            $table->unsignedInteger('late_count')->default(0);
            $table->timestamp('triggered_at');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->enum('action_taken', ['none', 'blocked', 'warning_sent', 'meeting_called', 'ignored'])->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'level'], 'idx_student_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_alerts');
    }
};
