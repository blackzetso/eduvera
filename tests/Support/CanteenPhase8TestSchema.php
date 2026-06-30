<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait CanteenPhase8TestSchema
{
    protected function extendCanteenPhase8TestSchema(): void
    {
        if (! Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('phone')->nullable();
            });
        }

        if (! Schema::hasTable('guardian_student')) {
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
        }

        if (! Schema::hasTable('guardian_notification_preferences')) {
            Schema::create('guardian_notification_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('guardian_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('student_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->boolean('notify_absence')->default(true);
                $table->boolean('notify_late')->default(true);
                $table->boolean('notify_whatsapp')->default(true);
                $table->boolean('notify_email')->default(false);
                $table->boolean('notify_in_app')->default(true);
                $table->boolean('notify_canteen_purchase')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('canteen_sales', 'primary_guardian_user_id')) {
            Schema::table('canteen_sales', function (Blueprint $table) {
                $table->foreignId('primary_guardian_user_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('canteen_parent_visibility_queue', 'guardian_id_ref')) {
            Schema::table('canteen_parent_visibility_queue', function (Blueprint $table) {
                $table->string('guardian_id_ref', 64)->nullable();
            });
        }

        if (! Schema::hasColumn('canteen_parent_visibility_queue', 'published_at')) {
            Schema::table('canteen_parent_visibility_queue', function (Blueprint $table) {
                $table->timestamp('published_at')->nullable();
            });
        }

        if (! Schema::hasColumn('canteen_parent_visibility_queue', 'notification_status')) {
            Schema::table('canteen_parent_visibility_queue', function (Blueprint $table) {
                $table->string('notification_status', 16)->default('none');
                $table->unsignedSmallInteger('notification_attempts')->default(0);
                $table->text('last_notification_error')->nullable();
                $table->timestamp('notified_at')->nullable();
            });
        }

        if (! Schema::hasTable('canteen_finance_entries')) {
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
                $table->unique(['sale_id', 'ledger_scope', 'entry_type']);
            });
        }
    }
}
