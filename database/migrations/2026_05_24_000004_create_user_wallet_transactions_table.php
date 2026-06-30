<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('user_wallets')->cascadeOnDelete();
            $table->enum('type', ['credit', 'debit', 'transfer_in', 'transfer_out']);
            $table->decimal('amount', 10, 2);
            $table->string('description');
            $table->foreignId('from_wallet_id')->nullable()->constrained('user_wallets')->nullOnDelete();
            $table->foreignId('to_wallet_id')->nullable()->constrained('user_wallets')->nullOnDelete();
            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_wallet_transactions');
    }
};
