<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dova_faq_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name_en');
            $table->string('name_ar');
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('dova_knowledge_gaps', function (Blueprint $table) {
            $table->id();
            $table->string('topic');
            $table->string('topic_slug')->unique();
            $table->unsignedInteger('frequency')->default(1);
            $table->timestamp('last_asked_at')->nullable();
            $table->string('portal', 32)->default('public');
            $table->string('role', 32)->default('guest');
            $table->string('suggested_category')->nullable();
            $table->string('status')->default('open');
            $table->string('priority')->default('medium');
            $table->json('sample_questions')->nullable();
            $table->foreignId('resolved_faq_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index('frequency');
        });

        Schema::create('dova_faqs', function (Blueprint $table) {
            $table->id();
            $table->text('question_en');
            $table->text('question_ar')->nullable();
            $table->text('answer_en');
            $table->text('answer_ar')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('dova_faq_categories')->nullOnDelete();
            $table->json('tags')->nullable();
            $table->string('status')->default('draft');
            $table->string('source')->default('manual');
            $table->foreignId('knowledge_gap_id')->nullable()->constrained('dova_knowledge_gaps')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('not_helpful_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index('source');
        });

        Schema::table('dova_knowledge_gaps', function (Blueprint $table) {
            $table->foreign('resolved_faq_id')->references('id')->on('dova_faqs')->nullOnDelete();
        });

        Schema::create('dova_faq_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('query_id')->nullable()->constrained('dova_knowledge_queries')->nullOnDelete();
            $table->foreignId('faq_id')->nullable()->constrained('dova_faqs')->nullOnDelete();
            $table->boolean('helpful');
            $table->text('question')->nullable();
            $table->string('portal', 32)->default('public');
            $table->string('role', 32)->default('guest');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['faq_id', 'helpful']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dova_faq_feedback');
        Schema::table('dova_knowledge_gaps', function (Blueprint $table) {
            $table->dropForeign(['resolved_faq_id']);
        });
        Schema::dropIfExists('dova_faqs');
        Schema::dropIfExists('dova_knowledge_gaps');
        Schema::dropIfExists('dova_faq_categories');
    }
};
