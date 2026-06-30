<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id_ref', 'is_active'], 'canteen_stu_blk_prod_active_idx');
            $table->unique(['student_id_ref', 'product_id'], 'canteen_stu_blk_prod_unique');
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
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id_ref', 'is_active'], 'canteen_stu_blk_cat_active_idx');
            $table->unique(['student_id_ref', 'category_id'], 'canteen_stu_blk_cat_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canteen_student_blocked_categories');
        Schema::dropIfExists('canteen_student_blocked_products');
    }
};
