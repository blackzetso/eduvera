<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('canteen_sales', function (Blueprint $table) {
            $table->foreignId('student_user_id')
                ->nullable()
                ->after('student_id_ref')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('student_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('canteen_sales', function (Blueprint $table) {
            $table->dropForeign(['student_user_id']);
            $table->dropIndex(['student_user_id']);
            $table->dropColumn('student_user_id');
        });
    }
};
