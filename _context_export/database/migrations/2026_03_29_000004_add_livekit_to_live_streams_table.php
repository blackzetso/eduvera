<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Modify the provider enum to include 'livekit'
        DB::statement("ALTER TABLE live_streams MODIFY COLUMN provider ENUM('none','teams','zoom','livekit') NOT NULL DEFAULT 'none'");

        Schema::table('live_streams', function (Blueprint $table) {
            $table->string('livekit_room_name')->nullable()->after('zoom_meeting_id');
        });
    }

    public function down(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropColumn('livekit_room_name');
        });

        DB::statement("ALTER TABLE live_streams MODIFY COLUMN provider ENUM('none','teams','zoom') NOT NULL DEFAULT 'none'");
    }
};
