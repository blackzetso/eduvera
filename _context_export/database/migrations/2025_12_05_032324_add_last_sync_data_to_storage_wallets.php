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
        Schema::table('storage_wallets', function (Blueprint $table) {
            $table->decimal('last_synced_storage_gb', 12, 4)->default(0)->after('total_debited');
            $table->decimal('last_synced_bandwidth_gb', 12, 4)->default(0)->after('last_synced_storage_gb');
            $table->timestamp('last_synced_at')->nullable()->after('last_synced_bandwidth_gb');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('storage_wallets', function (Blueprint $table) {
            $table->dropColumn(['last_synced_storage_gb', 'last_synced_bandwidth_gb', 'last_synced_at']);
        });
    }
};
