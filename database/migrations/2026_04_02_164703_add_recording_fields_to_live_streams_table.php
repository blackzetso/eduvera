<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->enum('recording_type',   ['none', 'server', 'local'])->default('none')->after('status');
            $table->enum('recording_status', ['none', 'recording', 'uploading', 'ready', 'failed'])->default('none')->after('recording_type');
            $table->string('recording_path')->nullable()->after('recording_status');
            $table->float('recording_size_mb')->unsigned()->nullable()->after('recording_path');
            $table->string('video_url')->nullable()->after('recording_size_mb');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropColumn(['recording_type', 'recording_status', 'recording_path', 'recording_size_mb', 'video_url']);
        });
    }
};
