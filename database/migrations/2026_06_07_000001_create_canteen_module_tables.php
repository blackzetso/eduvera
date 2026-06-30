<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canteen_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('canteen_staff', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['manager', 'cashier']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->unique('user_id');
        });

        Schema::create('canteen_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });

        Schema::create('canteen_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->constrained('canteen_categories')->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('description')->nullable();
            $table->enum('unit', ['piece', 'pack', 'serving'])->default('piece');
            $table->decimal('selling_price', 10, 2);
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_restricted_default')->default(false);
            $table->json('restriction_tags')->nullable();
            $table->string('image_path')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->index(['category_id', 'is_active']);
        });

        Schema::create('canteen_student_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('student_id_ref')->unique();
            $table->string('student_name');
            $table->string('grade')->nullable();
            $table->string('class_name')->nullable();
            $table->decimal('daily_spending_limit', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });

        Schema::create('canteen_restriction_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('rule_type', [
                'block_category',
                'block_tag',
                'require_tag',
                'block_product',
                'max_qty_per_day',
            ]);
            $table->json('config');
            $table->enum('severity', ['block', 'warn'])->default('block');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });

        Schema::create('canteen_student_restriction_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('student_id_ref');
            $table->foreignUuid('rule_id')->constrained('canteen_restriction_rules')->cascadeOnDelete();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->unique(['student_id_ref', 'rule_id'], 'canteen_stu_restrict_uq');
            $table->index('student_id_ref', 'canteen_stu_restrict_ref_idx');
        });

        Schema::create('canteen_sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sale_number')->unique();
            $table->string('student_id_ref');
            $table->string('student_name');
            $table->string('grade')->nullable();
            $table->string('class_name')->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->enum('payment_method', ['wallet_ready'])->default('wallet_ready');
            $table->enum('status', ['completed', 'voided', 'pending_payment', 'failed'])->default('completed');
            $table->boolean('daily_limit_checked')->default(false);
            $table->boolean('restrictions_checked')->default(false);
            $table->boolean('limit_override_applied')->default(false);
            $table->text('limit_override_reason')->nullable();
            $table->foreignId('limit_override_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cashier_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('sold_at');
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->index(['student_id_ref', 'sold_at']);
            $table->index(['status', 'sold_at']);
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

        Schema::create('canteen_inventory_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('canteen_products')->restrictOnDelete();
            $table->enum('type', [
                'opening_stock',
                'purchase',
                'sale',
                'adjustment',
                'damage',
                'return',
            ]);
            $table->decimal('quantity_delta', 12, 3);
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['product_id', 'occurred_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('canteen_limit_override_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_id')->nullable()->constrained('canteen_sales')->nullOnDelete();
            $table->string('student_id_ref');
            $table->decimal('attempted_amount', 10, 2);
            $table->decimal('daily_limit', 10, 2);
            $table->decimal('remaining_before', 10, 2);
            $table->foreignId('override_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('canteen_wallet_ready_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_id')->unique()->constrained('canteen_sales')->cascadeOnDelete();
            $table->string('student_id_ref');
            $table->enum('transaction_type', ['debit'])->default('debit');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EGP');
            $table->enum('status', ['pending', 'posted', 'failed', 'cancelled'])->default('pending');
            $table->string('source_module')->default('canteen');
            $table->unsignedBigInteger('external_wallet_tx_id')->nullable();
            $table->string('idempotency_key')->unique();
            $table->text('failure_reason')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['status', 'created_at']);
            $table->index('student_id_ref');
        });

        Schema::create('canteen_parent_visibility_queue', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_id')->constrained('canteen_sales')->cascadeOnDelete();
            $table->string('student_id_ref');
            $table->string('guardian_id_ref')->nullable();
            $table->json('payload');
            $table->enum('visibility_status', ['pending', 'published', 'suppressed'])->default('pending');
            $table->timestamp('published_at')->nullable();
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
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canteen_audit_logs');
        Schema::dropIfExists('canteen_parent_visibility_queue');
        Schema::dropIfExists('canteen_wallet_ready_transactions');
        Schema::dropIfExists('canteen_limit_override_logs');
        Schema::dropIfExists('canteen_inventory_transactions');
        Schema::dropIfExists('canteen_sale_items');
        Schema::dropIfExists('canteen_sales');
        Schema::dropIfExists('canteen_student_restriction_assignments');
        Schema::dropIfExists('canteen_restriction_rules');
        Schema::dropIfExists('canteen_student_profiles');
        Schema::dropIfExists('canteen_products');
        Schema::dropIfExists('canteen_categories');
        Schema::dropIfExists('canteen_staff');
        Schema::dropIfExists('canteen_settings');
    }
};
