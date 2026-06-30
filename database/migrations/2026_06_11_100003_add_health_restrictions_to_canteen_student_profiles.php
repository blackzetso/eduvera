<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('canteen_student_profiles', function (Blueprint $table) {
            $table->json('health_restrictions')->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('canteen_student_profiles', function (Blueprint $table) {
            $table->dropColumn('health_restrictions');
        });
    }
};
