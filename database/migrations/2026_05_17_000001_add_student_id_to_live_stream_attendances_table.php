<?php

// Link live stream attendance rows to users (students).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_stream_attendances', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->after('live_stream_id')
                ->constrained('users')->nullOnDelete();
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::table('live_stream_attendances', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropColumn('student_id');
        });
    }
};
