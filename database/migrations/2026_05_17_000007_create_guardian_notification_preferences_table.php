<?php

// Per-guardian notification channel preferences for attendance.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->boolean('notify_absence')->default(true);
            $table->boolean('notify_late')->default(true);
            $table->boolean('notify_whatsapp')->default(true);
            $table->boolean('notify_email')->default(false);
            $table->boolean('notify_in_app')->default(true);
            $table->timestamps();

            $table->unique(['guardian_id', 'student_id'], 'guardian_student_notify_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_notification_preferences');
    }
};
