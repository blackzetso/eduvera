<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE live_streams MODIFY COLUMN provider ENUM('none','teams','zoom','livekit','google_meet') NOT NULL DEFAULT 'none'");

        Schema::table('live_streams', function (Blueprint $table) {
            $table->string('google_meet_space_name')->nullable()->after('livekit_room_name');
        });
    }

    public function down(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropColumn('google_meet_space_name');
        });

        DB::statement("ALTER TABLE live_streams MODIFY COLUMN provider ENUM('none','teams','zoom','livekit') NOT NULL DEFAULT 'none'");
    }
};
