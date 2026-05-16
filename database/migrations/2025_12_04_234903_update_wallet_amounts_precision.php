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
        // Update storage_wallets table
        Schema::table('storage_wallets', function (Blueprint $table) {
            $table->decimal('balance', 12, 6)->change(); // من 10,2 إلى 12,6
            $table->decimal('total_credited', 12, 6)->change();
            $table->decimal('total_debited', 12, 6)->change();
        });

        // Update wallet_transactions table
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->decimal('amount', 12, 6)->change(); // من 10,2 إلى 12,6
        });

        // Update wallet_recharge_requests table
        Schema::table('wallet_recharge_requests', function (Blueprint $table) {
            $table->decimal('amount', 12, 6)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert storage_wallets table
        Schema::table('storage_wallets', function (Blueprint $table) {
            $table->decimal('balance', 10, 2)->change();
            $table->decimal('total_credited', 10, 2)->change();
            $table->decimal('total_debited', 10, 2)->change();
        });

        // Revert wallet_transactions table
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->change();
        });

        // Revert wallet_recharge_requests table
        Schema::table('wallet_recharge_requests', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->change();
        });
    }
};
