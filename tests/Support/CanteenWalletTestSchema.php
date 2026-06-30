<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait CanteenWalletTestSchema
{
    protected function setUpCanteenWalletTestSchema(): void
    {
        Schema::dropAllTables();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('student_code')->nullable()->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('user_type')->default('student');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->foreignId('current_team_id')->nullable();
            $table->string('profile_photo_path', 2048)->nullable();
            $table->timestamps();
        });

        Schema::create('user_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('balance', 10, 2)->default(0);
            $table->decimal('total_credited', 10, 2)->default(0);
            $table->decimal('total_debited', 10, 2)->default(0);
            $table->timestamps();
            $table->unique('user_id');
        });

        Schema::create('user_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('user_wallets')->cascadeOnDelete();
            $table->string('type');
            $table->decimal('amount', 10, 2);
            $table->string('description');
            $table->string('source_module', 32)->nullable();
            $table->string('source_id', 64)->nullable();
            $table->foreignId('from_wallet_id')->nullable()->constrained('user_wallets')->nullOnDelete();
            $table->foreignId('to_wallet_id')->nullable()->constrained('user_wallets')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('canteen_sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sale_number')->unique();
            $table->string('student_id_ref');
            $table->foreignId('student_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('student_name');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('payment_method')->default('wallet_ready');
            $table->string('status')->default('pending_payment');
            $table->foreignId('cashier_user_id')->constrained('users');
            $table->timestamp('sold_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('canteen_wallet_ready_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_id')->unique()->constrained('canteen_sales')->cascadeOnDelete();
            $table->string('student_id_ref');
            $table->string('transaction_type')->default('debit');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EGP');
            $table->string('status')->default('pending');
            $table->string('source_module')->default('canteen');
            $table->unsignedBigInteger('external_wallet_tx_id')->nullable();
            $table->string('idempotency_key')->unique();
            $table->text('failure_reason')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }
}
