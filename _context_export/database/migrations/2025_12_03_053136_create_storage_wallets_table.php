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
        Schema::create('storage_wallets', function (Blueprint $table) {
            $table->id();
            $table->decimal('balance', 10, 2)->default(0); // Balance in USD
            $table->decimal('total_credited', 10, 2)->default(0);
            $table->decimal('total_debited', 10, 2)->default(0);
            $table->boolean('is_activated')->default(false);
            $table->boolean('initial_credit_granted')->default(false);
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storage_wallets');
    }
};
