<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('canteen_student_blocked_products', 'starts_at')) {
            Schema::table('canteen_student_blocked_products', function (Blueprint $table) {
                $table->timestamp('starts_at')->nullable()->after('is_active');
                $table->timestamp('expires_at')->nullable()->after('starts_at');
            });
        }

        if (! Schema::hasColumn('canteen_student_blocked_categories', 'starts_at')) {
            Schema::table('canteen_student_blocked_categories', function (Blueprint $table) {
                $table->timestamp('starts_at')->nullable()->after('is_active');
                $table->timestamp('expires_at')->nullable()->after('starts_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('canteen_student_blocked_products', function (Blueprint $table) {
            $table->dropColumn(['starts_at', 'expires_at']);
        });

        Schema::table('canteen_student_blocked_categories', function (Blueprint $table) {
            $table->dropColumn(['starts_at', 'expires_at']);
        });
    }
};
