<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->enum('classroom_dashboard', ['jitsi', 'livekit', 'hms'])
                  ->default('jitsi')
                  ->after('provider');
        });

        // Migrate existing records: livekit/hms providers → classroom_dashboard
        \DB::table('live_streams')->where('provider', 'livekit')->update(['classroom_dashboard' => 'livekit']);
        \DB::table('live_streams')->where('provider', 'hms')->update(['classroom_dashboard' => 'hms']);
    }

    public function down(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropColumn('classroom_dashboard');
        });
    }
};
