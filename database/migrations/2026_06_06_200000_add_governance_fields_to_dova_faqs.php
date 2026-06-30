<?php

use App\Models\DovaFaq;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dova_faqs', function (Blueprint $table) {
            $table->foreignId('owner_user_id')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('review_frequency_days')->default(180)->after('owner_user_id');
            $table->timestamp('last_reviewed_at')->nullable()->after('review_frequency_days');
            $table->timestamp('next_review_due_at')->nullable()->after('last_reviewed_at');
            $table->string('knowledge_status', 32)->default('active')->after('next_review_due_at');

            $table->index(['knowledge_status', 'next_review_due_at']);
            $table->index('owner_user_id');
        });

        DovaFaq::query()
            ->where('status', DovaFaq::STATUS_PUBLISHED)
            ->whereNull('last_reviewed_at')
            ->each(function (DovaFaq $faq) {
                $base = $faq->published_at ?? $faq->created_at ?? now();
                $faq->update([
                    'last_reviewed_at' => $base,
                    'next_review_due_at' => $base->copy()->addDays(180),
                    'knowledge_status' => DovaFaq::KNOWLEDGE_ACTIVE,
                ]);
            });

        DovaFaq::query()
            ->where('status', DovaFaq::STATUS_ARCHIVED)
            ->update(['knowledge_status' => DovaFaq::KNOWLEDGE_ARCHIVED]);
    }

    public function down(): void
    {
        Schema::table('dova_faqs', function (Blueprint $table) {
            $table->dropForeign(['owner_user_id']);
            $table->dropIndex(['knowledge_status', 'next_review_due_at']);
            $table->dropIndex(['owner_user_id']);
            $table->dropColumn([
                'owner_user_id',
                'review_frequency_days',
                'last_reviewed_at',
                'next_review_due_at',
                'knowledge_status',
            ]);
        });
    }
};
