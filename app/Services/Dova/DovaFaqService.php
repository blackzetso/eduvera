<?php

namespace App\Services\Dova;

use App\Models\DovaFaq;
use App\Models\DovaKnowledgeGap;
use App\Models\DovaKnowledgeQuery;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DovaFaqService
{
    public function __construct(
        protected DovaFaqCategoryService $categories,
        protected DovaKnowledgeSyncService $sync,
        protected DovaKnowledgeGapDetectionService $gaps,
        protected DovaFaqGovernanceService $governance,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $user = null): DovaFaq
    {
        $this->categories->ensureDefaults();

        $faq = DovaFaq::query()->create([
            'question_en' => $data['question_en'],
            'question_ar' => $data['question_ar'] ?? null,
            'answer_en' => $data['answer_en'],
            'answer_ar' => $data['answer_ar'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'tags' => $data['tags'] ?? [],
            'status' => $data['status'] ?? DovaFaq::STATUS_DRAFT,
            'source' => $data['source'] ?? 'manual',
            'knowledge_gap_id' => $data['knowledge_gap_id'] ?? null,
            'owner_user_id' => $data['owner_user_id'] ?? $user?->id,
            'review_frequency_days' => $data['review_frequency_days']
                ?? config('dova-knowledge-governance.default_review_frequency_days', 180),
            'knowledge_status' => DovaFaq::KNOWLEDGE_ACTIVE,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        if ($faq->knowledge_gap_id) {
            DovaKnowledgeGap::query()
                ->where('id', $faq->knowledge_gap_id)
                ->update(['status' => DovaKnowledgeGap::STATUS_IN_PROGRESS]);
        }

        if ($faq->status === DovaFaq::STATUS_PUBLISHED) {
            $this->publish($faq, $user);
        }

        return $faq->fresh(['category', 'creator', 'updater']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(DovaFaq $faq, array $data, ?User $user = null): DovaFaq
    {
        $faq->update([
            'question_en' => $data['question_en'] ?? $faq->question_en,
            'question_ar' => $data['question_ar'] ?? $faq->question_ar,
            'answer_en' => $data['answer_en'] ?? $faq->answer_en,
            'answer_ar' => $data['answer_ar'] ?? $faq->answer_ar,
            'category_id' => $data['category_id'] ?? $faq->category_id,
            'tags' => $data['tags'] ?? $faq->tags,
            'owner_user_id' => $data['owner_user_id'] ?? $faq->owner_user_id,
            'review_frequency_days' => $data['review_frequency_days'] ?? $faq->review_frequency_days,
            'updated_by' => $user?->id,
        ]);

        if (array_key_exists('review_frequency_days', $data) && $faq->last_reviewed_at) {
            $this->governance->scheduleReviewDates($faq->fresh(), $faq->last_reviewed_at);
        }

        return $faq->fresh(['category', 'creator', 'updater', 'owner']);
    }

    public function submitForReview(DovaFaq $faq, ?User $user = null): DovaFaq
    {
        $faq->update([
            'status' => DovaFaq::STATUS_REVIEW,
            'updated_by' => $user?->id,
        ]);

        return $faq;
    }

    public function publish(DovaFaq $faq, ?User $user = null): DovaFaq
    {
        $now = Carbon::now();

        $faq->update([
            'status' => DovaFaq::STATUS_PUBLISHED,
            'published_at' => $faq->published_at ?? $now,
            'knowledge_status' => DovaFaq::KNOWLEDGE_ACTIVE,
            'owner_user_id' => $faq->owner_user_id ?? $user?->id,
            'updated_by' => $user?->id,
        ]);

        $this->governance->scheduleReviewDates($faq->fresh(), $now);

        $this->sync->syncSource('faq');

        $faq->update(['indexed_at' => Carbon::now()]);

        if ($faq->knowledge_gap_id) {
            $gap = DovaKnowledgeGap::query()->find($faq->knowledge_gap_id);

            if ($gap) {
                $gap->update([
                    'status' => DovaKnowledgeGap::STATUS_RESOLVED,
                    'resolved_faq_id' => $faq->id,
                    'resolved_at' => $now,
                ]);

                $this->markGapQueriesAnswered($gap, $faq);
            }
        }

        $this->gaps->recalculatePriorities();

        return $faq->fresh();
    }

    public function markGapQueriesAnswered(DovaKnowledgeGap $gap, DovaFaq $faq): int
    {
        $normalizedSamples = collect($gap->sample_questions ?? [])
            ->map(fn (string $q) => $this->normalizeQuestion($q))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($normalizedSamples === []) {
            return 0;
        }

        return DovaKnowledgeQuery::query()
            ->where('answered', false)
            ->where(function ($query) use ($normalizedSamples) {
                foreach ($normalizedSamples as $sample) {
                    $query->orWhere('normalized_question', $sample);
                }
            })
            ->update([
                'answered' => true,
                'source_slug' => 'faq',
                'record_key' => (string) $faq->id,
                'matched_content' => $faq->question_en,
                'answer_preview' => Str::limit(strip_tags($faq->answer_en), 200),
            ]);
    }

    protected function normalizeQuestion(string $message): string
    {
        $message = trim($message);
        $message = preg_replace('/[؟?!.،,:;]+/u', '', $message);
        $message = preg_replace('/\s+/u', ' ', $message);

        return mb_strtolower($message ?? '');
    }

    public function archive(DovaFaq $faq, ?User $user = null): DovaFaq
    {
        $faq->update([
            'status' => DovaFaq::STATUS_ARCHIVED,
            'knowledge_status' => DovaFaq::KNOWLEDGE_ARCHIVED,
            'updated_by' => $user?->id,
        ]);

        $this->sync->syncSource('faq');

        return $faq;
    }

    public function delete(DovaFaq $faq): void
    {
        $faq->delete();
        $this->sync->syncSource('faq');
    }

    /**
     * @return array<string, mixed>
     */
    public function prefillFromGap(DovaKnowledgeGap $gap): array
    {
        $sample = ($gap->sample_questions ?? [])[0] ?? $gap->topic;
        $category = $this->categories->listForSelect();
        $categoryId = collect($category)->firstWhere('slug', $gap->suggested_category)['id'] ?? null;

        return [
            'question_en' => $sample,
            'question_ar' => null,
            'answer_en' => '',
            'answer_ar' => null,
            'category_id' => $categoryId,
            'tags' => array_filter([$gap->suggested_category, "frequency:{$gap->frequency}"]),
            'source' => 'knowledge_gap',
            'knowledge_gap_id' => $gap->id,
            'gap' => [
                'topic' => $gap->topic,
                'frequency' => $gap->frequency,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function publishedForKnowledge(string $locale): array
    {
        return DovaFaq::query()
            ->with('category')
            ->where('status', DovaFaq::STATUS_PUBLISHED)
            ->where('knowledge_status', '!=', DovaFaq::KNOWLEDGE_ARCHIVED)
            ->orderByDesc('published_at')
            ->get()
            ->map(function (DovaFaq $faq) use ($locale) {
                $isAr = $locale === 'ar';

                return [
                    'q' => $isAr ? ($faq->question_ar ?: $faq->question_en) : $faq->question_en,
                    'a' => $isAr ? ($faq->answer_ar ?: $faq->answer_en) : $faq->answer_en,
                    'cat' => $faq->category?->slug ?? 'general',
                    'dova_id' => $faq->id,
                    'knowledge_status' => $faq->knowledge_status ?? DovaFaq::KNOWLEDGE_ACTIVE,
                ];
            })
            ->all();
    }
}
