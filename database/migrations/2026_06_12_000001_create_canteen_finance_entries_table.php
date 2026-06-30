<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canteen_finance_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_id')->constrained('canteen_sales')->cascadeOnDelete();
            $table->string('entry_type', 32);
            $table->string('ledger_scope', 16);
            $table->foreignId('student_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('student_id_ref');
            $table->foreignId('guardian_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('household_key', 64)->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('direction', 8);
            $table->string('currency', 3)->default('EGP');
            $table->foreignUuid('wallet_settlement_id')->nullable()->constrained('canteen_wallet_ready_transactions')->nullOnDelete();
            $table->unsignedBigInteger('wallet_tx_id')->nullable();
            $table->json('inventory_transaction_ids')->nullable();
            $table->string('status', 16)->default('posted');
            $table->json('metadata')->nullable();
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->unique(['sale_id', 'ledger_scope', 'entry_type'], 'canteen_fin_entry_unique');
            $table->index(['student_user_id', 'posted_at']);
            $table->index(['guardian_user_id', 'posted_at']);
            $table->index('wallet_tx_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canteen_finance_entries');
    }
};
