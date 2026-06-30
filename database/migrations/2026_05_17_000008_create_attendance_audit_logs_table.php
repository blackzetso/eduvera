<?php

// Audit trail for attendance record changes.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('student_attendances')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('event', ['created', 'updated', 'deleted', 'excused', 'status_changed']);
            $table->json('old_values_json')->nullable();
            $table->json('new_values_json')->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['attendance_id', 'created_at'], 'idx_attendance_audit');
        });

        Schema::table('student_attendances', function (Blueprint $table) {
            $table->foreign('import_batch_id')
                ->references('id')
                ->on('attendance_import_batches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('student_attendances', function (Blueprint $table) {
            $table->dropForeign(['import_batch_id']);
        });

        Schema::dropIfExists('attendance_audit_logs');
    }
};
