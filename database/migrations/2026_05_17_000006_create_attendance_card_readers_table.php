<?php

// Magnetic card reader devices for gate attendance.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_card_readers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location')->nullable();
            $table->string('device_id', 100)->unique();
            $table->string('api_key_hash');
            $table->string('session_type', 50)->default('morning');
            $table->enum('default_status', ['present', 'late'])->default('present');
            $table->time('late_after_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->json('settings_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_card_readers');
    }
};
