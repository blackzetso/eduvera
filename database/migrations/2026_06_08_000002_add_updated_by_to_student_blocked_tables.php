<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('canteen_student_blocked_products', 'updated_by')) {
            Schema::table('canteen_student_blocked_products', function (Blueprint $table) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('canteen_student_blocked_categories', 'updated_by')) {
            Schema::table('canteen_student_blocked_categories', function (Blueprint $table) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('canteen_student_blocked_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
        });

        Schema::table('canteen_student_blocked_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
        });
    }
};
