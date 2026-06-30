<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('canteen_student_profiles', function (Blueprint $table) {
            $table->foreignId('primary_guardian_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->string('guardian_id_ref', 64)
                ->nullable()
                ->after('primary_guardian_user_id');

            $table->index('primary_guardian_user_id');
            $table->index('guardian_id_ref');
        });
    }

    public function down(): void
    {
        Schema::table('canteen_student_profiles', function (Blueprint $table) {
            $table->dropForeign(['primary_guardian_user_id']);
            $table->dropIndex(['primary_guardian_user_id']);
            $table->dropIndex(['guardian_id_ref']);
            $table->dropColumn(['primary_guardian_user_id', 'guardian_id_ref']);
        });
    }
};
