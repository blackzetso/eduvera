<?php

// Add nullable student profile columns to users.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('student_code')->nullable()->unique()->after('id');
            $table->string('first_name')->nullable()->after('name');
            $table->string('father_name')->nullable()->after('first_name')->index();
            $table->string('grandfather_name')->nullable()->after('father_name');
            $table->date('date_of_birth')->nullable()->after('phone');
            $table->enum('gender', ['male', 'female'])->nullable()->after('date_of_birth');
            $table->date('enrollment_date')->nullable()->after('gender');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'student_code',
                'first_name',
                'father_name',
                'grandfather_name',
                'date_of_birth',
                'gender',
                'enrollment_date',
            ]);
        });
    }
};
