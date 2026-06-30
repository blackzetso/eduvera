<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('canteen_sales', function (Blueprint $table) {
            $table->foreignId('primary_guardian_user_id')
                ->nullable()
                ->after('student_user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('primary_guardian_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('canteen_sales', function (Blueprint $table) {
            $table->dropForeign(['primary_guardian_user_id']);
            $table->dropIndex(['primary_guardian_user_id']);
            $table->dropColumn('primary_guardian_user_id');
        });
    }
};
