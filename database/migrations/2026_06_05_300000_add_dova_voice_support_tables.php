<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dova_knowledge_queries', function (Blueprint $table) {
            $table->string('input_method', 16)->default('text')->after('role');
            $table->string('detected_language', 8)->nullable()->after('input_method');

            $table->index('input_method');
            $table->index('detected_language');
        });

        Schema::create('dova_voice_recognitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('portal', 32)->default('public');
            $table->string('role', 32)->default('guest');
            $table->boolean('success')->default(false);
            $table->string('engine', 32)->default('web_speech');
            $table->string('detected_language', 8)->nullable();
            $table->text('transcript')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['success', 'created_at']);
            $table->index(['engine', 'created_at']);
            $table->index(['portal', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dova_voice_recognitions');

        Schema::table('dova_knowledge_queries', function (Blueprint $table) {
            $table->dropIndex(['input_method']);
            $table->dropIndex(['detected_language']);
            $table->dropColumn(['input_method', 'detected_language']);
        });
    }
};
