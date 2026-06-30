<?php

namespace App\Services\Dova;

use App\Models\DovaFaq;
use App\Models\DovaKnowledgeGap;
use App\Models\DovaKnowledgeQuery;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DovaUnansweredResolutionService
{
    public function __construct(
        protected DovaKnowledgeGapDetectionService $gaps,
        protected DovaFaqService $faqs,
        protected DovaFaqCategoryService $categories,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function pageData(array $filters = []): array
    {
        $this->categories->ensureDefaults();

        return [
            'questions' => $this->listUnanswered($filters),
            'stats' => $this->resolutionStats(),
            'categories' => $this->categories->listForSelect(),
            'filters' => $filters,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listUnanswered(array $filters = [], int $limit = 100): array
    {
        $query = DovaKnowledgeGap::query()
            ->with('faq')
            ->orderByDesc('frequency');

        $statusFilter = $filters['status'] ?? null;

        if ($statusFilter === 'ignored') {
            $query->where('status', DovaKnowledgeGap::STATUS_DISMISSED);
        } else {
            $query->whereIn('status', [
                DovaKnowledgeGap::STATUS_OPEN,
                DovaKnowledgeGap::STATUS_IN_PROGRESS,
            ]);

            if ($statusFilter) {
                $query->where(function ($q) use ($statusFilter) {
                    match ($statusFilter) {
                        'unanswered' => $q->where('status', DovaKnowledgeGap::STATUS_OPEN)->whereDoesntHave('faq'),
                        'draft' => $q->whereHas('faq', fn ($f) => $f->where('status', DovaFaq::STATUS_DRAFT)),
                        'pending_review' => $q->whereHas('faq', fn ($f) => $f->where('status', DovaFaq::STATUS_REVIEW)),
                        default => null,
                    };
                });
            }
        }

        if (! empty($filters['portal'])) {
            $query->where('portal', $filters['portal']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        return $query->limit($limit)->get()->map(fn (DovaKnowledgeGap $gap) => $this->formatGapRow($gap))->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function showGap(DovaKnowledgeGap $gap): array
    {
        $gap->load(['faq.category', 'resolvedFaq']);

        $prefill = $this->faqs->prefillFromGap($gap);
        $existingFaq = $gap->faq;

        return [
            'gap' => $this->formatGapRow($gap, detailed: true),
            'faq' => $existingFaq ? [
                'id' => $existingFaq->id,
                'question_en' => $existingFaq->question_en,
                'question_ar' => $existingFaq->question_ar,
                'answer_en' => $existingFaq->answer_en,
                'answer_ar' => $existingFaq->answer_ar,
                'category_id' => $existingFaq->category_id,
                'status' => $existingFaq->status,
            ] : null,
            'prefill' => $prefill,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveDraft(DovaKnowledgeGap $gap, array $data, ?User $user = null): DovaFaq
    {
        $this->assertResolvable($gap);

        $faq = $this->upsertFaqForGap($gap, $data, DovaFaq::STATUS_DRAFT, $user);

        $gap->update(['status' => DovaKnowledgeGap::STATUS_IN_PROGRESS]);

        return $faq;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function publishFaq(DovaKnowledgeGap $gap, array $data, ?User $user = null): DovaFaq
    {
        $this->assertResolvable($gap);

        if ($gap->resolved_faq_id) {
            throw ValidationException::withMessages([
                'gap' => 'تم نشر سؤال شائع لهذا السؤال مسبقاً.',
            ]);
        }

        $faq = $this->upsertFaqForGap($gap, $data, DovaFaq::STATUS_DRAFT, $user);

        return $this->faqs->publish($faq, $user)->fresh(['category', 'knowledgeGap']);
    }

    public function ignore(DovaKnowledgeGap $gap): void
    {
        if ($gap->status === DovaKnowledgeGap::STATUS_RESOLVED) {
            throw ValidationException::withMessages([
                'gap' => 'لا يمكن تجاهل سؤال تم حله بالفعل.',
            ]);
        }

        $gap->update(['status' => DovaKnowledgeGap::STATUS_DISMISSED]);
    }

    public function syncGaps(): int
    {
        return $this->gaps->syncFromQueryLogs();
    }

    /**
     * @return array<string, int|float>
     */
    public function resolutionStats(): array
    {
        $total = (int) DovaKnowledgeGap::query()->count();
        $open = (int) DovaKnowledgeGap::query()
            ->whereIn('status', [DovaKnowledgeGap::STATUS_OPEN, DovaKnowledgeGap::STATUS_IN_PROGRESS])
            ->count();
        $resolved = (int) DovaKnowledgeGap::query()
            ->where('status', DovaKnowledgeGap::STATUS_RESOLVED)
            ->count();
        $ignored = (int) DovaKnowledgeGap::query()
            ->where('status', DovaKnowledgeGap::STATUS_DISMISSED)
            ->count();
        $resolvedThisMonth = (int) DovaKnowledgeGap::query()
            ->where('status', DovaKnowledgeGap::STATUS_RESOLVED)
            ->where('resolved_at', '>=', now()->startOfMonth())
            ->count();
        $totalUnansweredQueries = (int) DovaKnowledgeQuery::query()->where('answered', false)->count();
        $conversionRate = $total > 0 ? round(($resolved / $total) * 100, 1) : 0.0;

        return [
            'totalGaps' => $total,
            'openQuestions' => $open,
            'resolvedQuestions' => $resolved,
            'ignoredQuestions' => $ignored,
            'resolvedThisMonth' => $resolvedThisMonth,
            'totalUnansweredQueries' => $totalUnansweredQueries,
            'conversionRate' => $conversionRate,
        ];
    }

    /**
     * @return array<int, array{topic: string, frequency: int}>
     */
    public function topRepeatedUnanswered(int $limit = 5): array
    {
        return DovaKnowledgeGap::query()
            ->whereIn('status', [DovaKnowledgeGap::STATUS_OPEN, DovaKnowledgeGap::STATUS_IN_PROGRESS])
            ->orderByDesc('frequency')
            ->limit($limit)
            ->get()
            ->map(fn (DovaKnowledgeGap $g) => [
                'topic' => $g->topic,
                'frequency' => $g->frequency,
            ])
            ->all();
    }

    protected function assertResolvable(DovaKnowledgeGap $gap): void
    {
        if (! in_array($gap->status, [DovaKnowledgeGap::STATUS_OPEN, DovaKnowledgeGap::STATUS_IN_PROGRESS], true)) {
            throw ValidationException::withMessages([
                'gap' => 'لا يمكن معالجة هذا السؤال في حالته الحالية.',
            ]);
        }

        if ($gap->resolved_faq_id) {
            throw ValidationException::withMessages([
                'gap' => 'تم نشر سؤال شائع لهذا السؤال مسبقاً.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function upsertFaqForGap(DovaKnowledgeGap $gap, array $data, string $status, ?User $user): DovaFaq
    {
        $existing = $gap->faq;

        $payload = [
            'question_en' => $data['question_en'],
            'question_ar' => $data['question_ar'] ?? null,
            'answer_en' => $data['answer_en'],
            'answer_ar' => $data['answer_ar'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'tags' => $data['tags'] ?? [],
            'status' => $status,
            'source' => 'knowledge_gap',
            'knowledge_gap_id' => $gap->id,
        ];

        if ($existing) {
            if ($existing->status === DovaFaq::STATUS_PUBLISHED) {
                throw ValidationException::withMessages([
                    'faq' => 'يوجد سؤال شائع منشور مرتبط بهذا السؤال.',
                ]);
            }

            return $this->faqs->update($existing, $payload, $user);
        }

        $publishedFaq = DovaFaq::query()
            ->where('knowledge_gap_id', $gap->id)
            ->where('status', DovaFaq::STATUS_PUBLISHED)
            ->exists();

        if ($publishedFaq) {
            throw ValidationException::withMessages([
                'faq' => 'يوجد سؤال شائع منشور مرتبط بهذا السؤال.',
            ]);
        }

        return $this->faqs->create($payload, $user);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatGapRow(DovaKnowledgeGap $gap, bool $detailed = false): array
    {
        $firstAsked = $gap->first_asked_at ?? $gap->created_at;

        $row = [
            'id' => $gap->id,
            'question' => ($gap->sample_questions ?? [])[0] ?? $gap->topic,
            'topic' => $gap->topic,
            'frequency' => $gap->frequency,
            'portal' => $gap->portal,
            'portalLabel' => $this->portalLabel($gap->portal),
            'role' => $gap->role,
            'suggestedCategory' => $gap->suggested_category,
            'priority' => $gap->priority,
            'status' => $gap->resolutionStatus(),
            'statusLabel' => $this->statusLabel($gap->resolutionStatus()),
            'firstSeen' => $firstAsked?->format('Y-m-d H:i') ?? '—',
            'lastSeen' => $gap->last_asked_at?->format('Y-m-d H:i') ?? '—',
            'sourceModule' => $this->portalLabel($gap->portal),
            'hasFaq' => $gap->faq !== null,
            'faqId' => $gap->faq?->id ?? $gap->resolved_faq_id,
            'sampleQuestions' => $gap->sample_questions ?? [],
        ];

        if ($detailed) {
            $row['knowledgeGapId'] = $gap->id;
            $row['resolvedFaqId'] = $gap->resolved_faq_id;
        }

        return $row;
    }

    protected function portalLabel(?string $portal): string
    {
        return match ($portal) {
            'admin' => 'الإدارة',
            'guardian' => 'أولياء الأمور',
            'teacher' => 'المدرسون',
            'student' => 'الطلاب',
            default => 'الموقع العام',
        };
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'مسودة',
            'pending_review' => 'قيد المراجعة',
            'published' => 'منشور',
            'ignored' => 'متجاهل',
            default => 'بلا إجابة',
        };
    }
}
