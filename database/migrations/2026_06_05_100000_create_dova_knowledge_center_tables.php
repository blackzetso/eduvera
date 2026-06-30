<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dova_knowledge_sources', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name_en');
            $table->string('name_ar');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('record_count')->default(0);
            $table->string('status')->default('not_indexed');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dova_knowledge_records', function (Blueprint $table) {
            $table->id();
            $table->string('source_slug');
            $table->string('record_key');
            $table->string('title')->nullable();
            $table->text('content');
            $table->string('locale', 5)->default('en');
            $table->timestamp('content_updated_at')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            $table->unique(['source_slug', 'record_key', 'locale']);
            $table->index(['source_slug', 'locale']);
        });

        Schema::create('dova_knowledge_queries', function (Blueprint $table) {
            $table->id();
            $table->text('question');
            $table->string('normalized_question', 500)->nullable();
            $table->string('portal', 32)->default('public');
            $table->string('role', 32)->default('guest');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('answered')->default(false);
            $table->string('intent', 32)->nullable();
            $table->string('source_slug')->nullable();
            $table->string('record_key')->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->unsignedInteger('response_ms')->nullable();
            $table->text('matched_content')->nullable();
            $table->text('answer_preview')->nullable();
            $table->timestamps();

            $table->index(['answered', 'created_at']);
            $table->index('normalized_question');
            $table->index(['portal', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dova_knowledge_queries');
        Schema::dropIfExists('dova_knowledge_records');
        Schema::dropIfExists('dova_knowledge_sources');
    }
};
