<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('live_stream_quizzes', function (Blueprint $table) {
            $table->foreignId('exam_session_id')
                  ->nullable()
                  ->after('live_stream_id')
                  ->constrained('live_stream_exam_sessions')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('live_stream_quizzes', function (Blueprint $table) {
            $table->dropForeign(['exam_session_id']);
            $table->dropColumn('exam_session_id');
        });
    }
};
