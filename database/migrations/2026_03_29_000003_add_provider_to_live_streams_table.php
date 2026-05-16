<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->enum('provider', ['none', 'teams', 'zoom'])->default('none')->after('status');
            $table->string('zoom_meeting_id')->nullable()->after('teams_meeting_id');
        });
    }

    public function down(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropColumn(['provider', 'zoom_meeting_id']);
        });
    }
};
