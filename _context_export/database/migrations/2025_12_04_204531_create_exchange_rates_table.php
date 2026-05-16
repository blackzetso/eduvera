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
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('from_currency', 3)->default('USD'); // e.g., USD
            $table->string('to_currency', 3)->default('EGP'); // e.g., EGP
            $table->decimal('rate', 10, 4); // Exchange rate (e.g., 31.0000)
            $table->string('source')->nullable(); // API source (e.g., exchangerate-api.com)
            $table->timestamp('fetched_at')->nullable(); // When the rate was fetched
            $table->boolean('is_active')->default(true); // Active rate
            $table->timestamps();
            
            // Ensure only one active rate per currency pair
            $table->unique(['from_currency', 'to_currency', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
