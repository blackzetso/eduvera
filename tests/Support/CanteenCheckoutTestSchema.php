<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait CanteenCheckoutTestSchema
{
    protected function setUpCanteenCheckoutTestSchema(): void
    {
        Schema::dropAllTables();

        Schema::create('canteen_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('student_code')->nullable()->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('user_type')->default('student');
            $table->string('student_status', 32)->default('active');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->foreignId('current_team_id')->nullable();
            $table->string('profile_photo_path', 2048)->nullable();
            $table->timestamps();
        });

        Schema::create('guardian_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('relationship_type', 32)->default('guardian');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_emergency_contact')->default(false);
            $table->boolean('is_pickup_authorized')->default(true);
            $table->boolean('is_financial_responsible')->default(false);
            $table->timestamps();
            $table->unique(['guardian_id', 'student_id']);
        });

        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('academic_year', 16)->default('2025-2026');
            $table->string('grade_name')->nullable();
            $table->string('class_name')->nullable();
            $table->date('enrollment_date');
            $table->string('status', 32)->default('active');
            $table->boolean('is_current')->default(true);
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

        Schema::create('canteen_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('canteen_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->constrained('canteen_categories')->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('unit')->default('piece');
            $table->decimal('selling_price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('canteen_inventory_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('canteen_products')->restrictOnDelete();
            $table->string('type');
            $table->decimal('quantity_delta', 12, 3);
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('canteen_student_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('primary_guardian_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guardian_id_ref', 64)->nullable();
            $table->string('student_id_ref')->unique();
            $table->string('student_name');
            $table->string('grade')->nullable();
            $table->string('class_name')->nullable();
            $table->decimal('daily_spending_limit', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->json('health_restrictions')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('canteen_restriction_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('rule_type');
            $table->json('config');
            $table->string('severity')->default('block');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('canteen_student_restriction_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('student_id_ref');
            $table->foreignUuid('rule_id')->constrained('canteen_restriction_rules')->cascadeOnDelete();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('canteen_student_blocked_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('student_id_ref');
            $table->foreignUuid('product_id')->constrained('canteen_products')->cascadeOnDelete();
            $table->string('block_source')->default('parent_request');
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id_ref', 'product_id']);
        });

        Schema::create('canteen_student_blocked_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('student_id_ref');
            $table->foreignUuid('category_id')->constrained('canteen_categories')->cascadeOnDelete();
            $table->string('block_source')->default('parent_request');
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id_ref', 'category_id']);
        });

        Schema::create('canteen_sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sale_number')->unique();
            $table->string('student_id_ref');
            $table->foreignId('student_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('primary_guardian_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('student_name');
            $table->string('grade')->nullable();
            $table->string('class_name')->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('payment_method')->default('wallet_ready');
            $table->string('status')->default('pending_payment');
            $table->boolean('daily_limit_checked')->default(false);
            $table->boolean('restrictions_checked')->default(false);
            $table->boolean('limit_override_applied')->default(false);
            $table->text('limit_override_reason')->nullable();
            $table->foreignId('limit_override_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cashier_user_id')->constrained('users');
            $table->timestamp('sold_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('canteen_sale_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_id')->constrained('canteen_sales')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('canteen_products')->restrictOnDelete();
            $table->string('product_name');
            $table->string('product_sku');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('quantity', 12, 3);
            $table->decimal('line_total', 10, 2);
            $table->timestamps();
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

        Schema::create('canteen_parent_visibility_queue', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_id')->constrained('canteen_sales')->cascadeOnDelete();
            $table->string('student_id_ref');
            $table->string('guardian_id_ref', 64)->nullable();
            $table->json('payload');
            $table->string('visibility_status')->default('pending');
            $table->string('notification_status', 16)->default('none');
            $table->unsignedSmallInteger('notification_attempts')->default(0);
            $table->text('last_notification_error')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('canteen_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('subject_type');
            $table->uuid('subject_id');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('canteen_limit_override_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_id')->nullable()->constrained('canteen_sales')->nullOnDelete();
            $table->string('student_id_ref');
            $table->decimal('attempted_amount', 10, 2);
            $table->decimal('daily_limit', 10, 2);
            $table->decimal('remaining_before', 10, 2);
            $table->foreignId('override_by')->constrained('users');
            $table->text('reason');
            $table->timestamp('created_at')->useCurrent();
        });
    }
}
