<?php

// Official student attendance records (per session per day).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('session_type', 50)->default('class');
            $table->string('session_label', 100)->nullable();
            $table->foreignId('timetable_period_id')->nullable()->constrained('timetable_periods')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->unsignedInteger('period_number')->nullable();
            $table->foreignId('live_stream_id')->nullable()->constrained('live_streams')->nullOnDelete();
            $table->enum('status', ['present', 'absent', 'late', 'excused']);
            $table->time('arrival_time')->nullable();
            $table->unsignedInteger('minutes_late')->nullable();
            $table->string('excused_reason')->nullable();
            $table->text('notes')->nullable();
            $table->enum('source', ['manual', 'excel', 'card', 'mobile_app', 'live_stream', 'api'])->default('manual');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('card_reader_id', 100)->nullable();
            $table->unsignedBigInteger('import_batch_id')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->unique(
                ['student_id', 'attendance_date', 'session_type', 'timetable_period_id'],
                'unique_attendance_per_session'
            );
            $table->index(['attendance_date', 'status'], 'idx_date_status');
            $table->index(['category_id', 'attendance_date'], 'idx_category_date');
            $table->index(['timetable_period_id', 'attendance_date'], 'idx_period_date');
            $table->index(['student_id', 'attendance_date'], 'idx_student_date');
            $table->index('live_stream_id', 'idx_live_stream');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_attendances');
    }
};
