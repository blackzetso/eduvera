<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardian_student', function (Blueprint $table) {
            $table->string('relationship_type', 32)->default('guardian')->after('student_id');
            $table->boolean('is_primary')->default(false)->after('relationship_type');
            $table->boolean('is_emergency_contact')->default(false)->after('is_primary');
            $table->boolean('is_pickup_authorized')->default(true)->after('is_emergency_contact');
            $table->boolean('is_financial_responsible')->default(false)->after('is_pickup_authorized');
        });
    }

    public function down(): void
    {
        Schema::table('guardian_student', function (Blueprint $table) {
            $table->dropColumn([
                'relationship_type',
                'is_primary',
                'is_emergency_contact',
                'is_pickup_authorized',
                'is_financial_responsible',
            ]);
        });
    }
};
