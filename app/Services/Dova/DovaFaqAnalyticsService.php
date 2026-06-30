<?php

namespace App\Services\Dova;

use App\Models\DovaFaq;
use App\Models\DovaFaqFeedback;
use App\Models\DovaKnowledgeGap;
use App\Models\DovaKnowledgeQuery;
use Illuminate\Support\Facades\DB;

class DovaFaqAnalyticsService
{
    public function __construct(
        protected DovaFaqCategoryService $categories,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $this->categories->ensureDefaults();

        $resolution = app(DovaUnansweredResolutionService::class)->resolutionStats();
        $governance = app(DovaFaqGovernanceService::class)->healthMetrics();

        $recent = DovaFaq::query()
            ->with('category')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (DovaFaq $f) => $this->formatFaqRow($f))
            ->all();

        $mostUsed = DovaFaq::query()
            ->where('status', DovaFaq::STATUS_PUBLISHED)
            ->orderByDesc('view_count')
            ->limit(5)
            ->get()
            ->map(fn (DovaFaq $f) => [
                'id' => $f->id,
                'question' => $f->question_en,
                'views' => $f->view_count,
            ])
            ->all();

        return [
            'total' => DovaFaq::query()->count(),
            'active' => DovaFaq::query()->where('status', DovaFaq::STATUS_PUBLISHED)->count(),
            'draft' => DovaFaq::query()->where('status', DovaFaq::STATUS_DRAFT)->count(),
            'review' => DovaFaq::query()->where('status', DovaFaq::STATUS_REVIEW)->count(),
            'fromGaps' => DovaFaq::query()->where('source', 'knowledge_gap')->count(),
            'recent' => $recent,
            'mostUsed' => $mostUsed,
            'resolution' => $resolution,
            'governance' => $governance,
            'topRepeatedUnanswered' => app(DovaUnansweredResolutionService::class)->topRepeatedUnanswered(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function faqAnalytics(): array
    {
        $mostViewed = DovaFaq::query()
            ->where('status', DovaFaq::STATUS_PUBLISHED)
            ->orderByDesc('view_count')
            ->limit(5)
            ->get(['id', 'question_en', 'view_count'])
            ->all();

        $mostHelpful = DovaFaq::query()
            ->where('status', DovaFaq::STATUS_PUBLISHED)
            ->orderByDesc('helpful_count')
            ->limit(5)
            ->get(['id', 'question_en', 'helpful_count'])
            ->all();

        $leastHelpful = DovaFaq::query()
            ->where('status', DovaFaq::STATUS_PUBLISHED)
            ->where('not_helpful_count', '>', 0)
            ->orderByDesc('not_helpful_count')
            ->limit(5)
            ->get(['id', 'question_en', 'not_helpful_count'])
            ->all();

        $growth = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i)->startOfDay();
            $count = DovaFaq::query()
                ->where('status', DovaFaq::STATUS_PUBLISHED)
                ->whereDate('published_at', $day)
                ->count();
            $growth[] = ['date' => $day->format('M d'), 'count' => $count];
        }

        return [
            'mostViewed' => $mostViewed,
            'mostHelpful' => $mostHelpful,
            'leastHelpful' => $leastHelpful,
            'knowledgeGrowth' => $growth,
        ];
    }

    public function knowledgeCoveragePercent(): int
    {
        $total = (int) DovaKnowledgeQuery::query()->count();
        if ($total === 0) {
            return 0;
        }

        $answered = (int) DovaKnowledgeQuery::query()->where('answered', true)->count();

        return (int) round(($answered / $total) * 100);
    }

    /**
     * @return array<int, array{topic: string, frequency: int}>
     */
    public function topMissingTopics(int $limit = 10): array
    {
        return DovaKnowledgeGap::query()
            ->whereIn('status', [DovaKnowledgeGap::STATUS_OPEN, DovaKnowledgeGap::STATUS_IN_PROGRESS])
            ->orderByDesc('frequency')
            ->limit($limit)
            ->get()
            ->map(fn (DovaKnowledgeGap $g) => [
                'topic' => $g->topic,
                'frequency' => $g->frequency,
                'priority' => $g->priority,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listFaqs(array $filters = []): array
    {
        $query = DovaFaq::query()->with(['category', 'creator', 'updater', 'owner']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['knowledge_status'])) {
            $query->where('knowledge_status', $filters['knowledge_status']);
        }

        if (! empty($filters['owner_user_id'])) {
            $query->where('owner_user_id', $filters['owner_user_id']);
        }

        if ($filters['review_filter'] ?? null) {
            match ($filters['review_filter']) {
                'needs_review' => $query->where('knowledge_status', DovaFaq::KNOWLEDGE_NEEDS_REVIEW),
                'overdue' => $query->where('knowledge_status', DovaFaq::KNOWLEDGE_NEEDS_REVIEW)
                    ->where('next_review_due_at', '<', now()),
                'active' => $query->where('knowledge_status', DovaFaq::KNOWLEDGE_ACTIVE),
                'archived' => $query->where(function ($q) {
                    $q->where('status', DovaFaq::STATUS_ARCHIVED)
                        ->orWhere('knowledge_status', DovaFaq::KNOWLEDGE_ARCHIVED);
                }),
                'deprecated' => $query->where('knowledge_status', DovaFaq::KNOWLEDGE_DEPRECATED),
                default => null,
            };
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('question_en', 'like', $search)
                    ->orWhere('question_ar', 'like', $search);
            });
        }

        return $query
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->through(fn (DovaFaq $f) => $this->formatFaqRow($f))
            ->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatFaqRow(DovaFaq $f): array
    {
        return [
            'id' => $f->id,
            'questionEn' => $f->question_en,
            'questionAr' => $f->question_ar,
            'status' => $f->status,
            'statusLabel' => $this->statusLabel($f->status),
            'category' => $f->category?->name_ar,
            'source' => $f->source,
            'tags' => $f->tags ?? [],
            'views' => $f->view_count,
            'helpful' => $f->helpful_count,
            'notHelpful' => $f->not_helpful_count,
            'createdBy' => $f->creator?->name,
            'updatedBy' => $f->updater?->name,
            'createdAt' => $f->created_at?->format('Y-m-d H:i'),
            'updatedAt' => $f->updated_at?->format('Y-m-d H:i'),
            'publishedAt' => $f->published_at?->format('Y-m-d H:i'),
            'knowledgeGapId' => $f->knowledge_gap_id,
            'owner' => $f->owner?->name,
            'ownerId' => $f->owner_user_id,
            'lastReviewed' => $f->last_reviewed_at?->format('Y-m-d') ?? '—',
            'nextReview' => $f->next_review_due_at?->format('Y-m-d') ?? '—',
            'knowledgeStatus' => $f->knowledge_status,
            'knowledgeStatusLabel' => app(DovaFaqGovernanceService::class)->knowledgeStatusLabel($f->knowledge_status),
            'reviewFrequencyDays' => $f->review_frequency_days,
        ];
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            DovaFaq::STATUS_DRAFT => 'مسودة',
            DovaFaq::STATUS_REVIEW => 'قيد المراجعة',
            DovaFaq::STATUS_PUBLISHED => 'منشور',
            DovaFaq::STATUS_ARCHIVED => 'مؤرشف',
            default => $status,
        };
    }

    public function recordFeedback(
        bool $helpful,
        ?int $queryId = null,
        ?int $faqId = null,
        ?string $question = null,
        string $portal = 'public',
        string $role = 'guest',
        ?int $userId = null,
    ): void {
        DovaFaqFeedback::query()->create([
            'query_id' => $queryId,
            'faq_id' => $faqId,
            'helpful' => $helpful,
            'question' => $question,
            'portal' => $portal,
            'role' => $role,
            'user_id' => $userId,
        ]);

        if ($faqId) {
            $column = $helpful ? 'helpful_count' : 'not_helpful_count';
            DovaFaq::query()->where('id', $faqId)->increment($column);
        }
    }
}
