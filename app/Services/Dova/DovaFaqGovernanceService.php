<?php

namespace App\Services\Dova;

use App\Models\DovaFaq;
use App\Models\User;
use App\Notifications\DovaFaqKnowledgeReviewNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class DovaFaqGovernanceService
{
    /**
     * @return array<string, mixed>
     */
    public function healthMetrics(): array
    {
        $published = DovaFaq::query()->where('status', DovaFaq::STATUS_PUBLISHED);

        $total = (int) DovaFaq::query()->count();
        $active = (int) (clone $published)->where('knowledge_status', DovaFaq::KNOWLEDGE_ACTIVE)->count();
        $dueForReview = (int) (clone $published)
            ->whereIn('knowledge_status', [DovaFaq::KNOWLEDGE_ACTIVE, DovaFaq::KNOWLEDGE_NEEDS_REVIEW])
            ->where('next_review_due_at', '<=', now())
            ->count();
        $needsReview = (int) (clone $published)->where('knowledge_status', DovaFaq::KNOWLEDGE_NEEDS_REVIEW)->count();
        $deprecated = (int) (clone $published)->where('knowledge_status', DovaFaq::KNOWLEDGE_DEPRECATED)->count();
        $archived = (int) DovaFaq::query()
            ->where(function ($q) {
                $q->where('status', DovaFaq::STATUS_ARCHIVED)
                    ->orWhere('knowledge_status', DovaFaq::KNOWLEDGE_ARCHIVED);
            })
            ->count();

        $reviewedThisMonth = (int) DovaFaq::query()
            ->where('last_reviewed_at', '>=', now()->startOfMonth())
            ->count();

        $overdue = (int) (clone $published)
            ->where('knowledge_status', DovaFaq::KNOWLEDGE_NEEDS_REVIEW)
            ->where('next_review_due_at', '<', now()->subDays(config('dova-knowledge-governance.reminder_days_overdue', 30)))
            ->count();

        $publishedWithDates = (clone $published)->whereNotNull('published_at')->get();
        $avgAgeDays = $publishedWithDates->isEmpty()
            ? 0
            : (int) round($publishedWithDates->avg(fn (DovaFaq $f) => $f->published_at->diffInDays(now())));

        return [
            'totalFaqs' => $total,
            'activeFaqs' => $active,
            'dueForReview' => $dueForReview,
            'needsReview' => $needsReview,
            'deprecatedFaqs' => $deprecated,
            'archivedFaqs' => $archived,
            'reviewedThisMonth' => $reviewedThisMonth,
            'overdueReviews' => $overdue,
            'averageFaqAgeDays' => $avgAgeDays,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function governanceDashboard(): array
    {
        return [
            'health' => $this->healthMetrics(),
            'reviewQueue' => $this->reviewQueue(15),
            'overdueItems' => $this->overdueItems(10),
            'ownershipDistribution' => $this->ownershipDistribution(),
            'categoryHealth' => $this->categoryHealth(),
            'agingAnalysis' => $this->agingAnalysis(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function reviewQueue(int $limit = 20): array
    {
        return DovaFaq::query()
            ->with(['category', 'owner'])
            ->where('status', DovaFaq::STATUS_PUBLISHED)
            ->where(function ($q) {
                $q->where('knowledge_status', DovaFaq::KNOWLEDGE_NEEDS_REVIEW)
                    ->orWhere(function ($q2) {
                        $q2->where('knowledge_status', DovaFaq::KNOWLEDGE_ACTIVE)
                            ->where('next_review_due_at', '<=', now());
                    });
            })
            ->orderBy('next_review_due_at')
            ->limit($limit)
            ->get()
            ->map(fn (DovaFaq $f) => $this->formatGovernanceRow($f))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function overdueItems(int $limit = 20): array
    {
        return DovaFaq::query()
            ->with(['category', 'owner'])
            ->where('status', DovaFaq::STATUS_PUBLISHED)
            ->where('knowledge_status', DovaFaq::KNOWLEDGE_NEEDS_REVIEW)
            ->where('next_review_due_at', '<', now())
            ->orderBy('next_review_due_at')
            ->limit($limit)
            ->get()
            ->map(fn (DovaFaq $f) => $this->formatGovernanceRow($f))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function ownershipDistribution(): array
    {
        $rows = DovaFaq::query()
            ->where('status', DovaFaq::STATUS_PUBLISHED)
            ->whereNotNull('owner_user_id')
            ->select('owner_user_id', DB::raw('COUNT(*) as total'))
            ->groupBy('owner_user_id')
            ->get();

        $ownerNames = User::query()
            ->whereIn('id', $rows->pluck('owner_user_id'))
            ->pluck('name', 'id');

        return $rows
            ->map(fn ($row) => [
                'ownerId' => $row->owner_user_id,
                'ownerName' => $ownerNames[$row->owner_user_id] ?? '—',
                'total' => (int) $row->total,
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function categoryHealth(): array
    {
        return DovaFaq::query()
            ->with('category')
            ->where('status', DovaFaq::STATUS_PUBLISHED)
            ->get()
            ->groupBy('category_id')
            ->map(function ($group) {
                $category = $group->first()->category;

                return [
                    'category' => $category?->name_ar ?? 'عام',
                    'total' => $group->count(),
                    'active' => $group->where('knowledge_status', DovaFaq::KNOWLEDGE_ACTIVE)->count(),
                    'needsReview' => $group->where('knowledge_status', DovaFaq::KNOWLEDGE_NEEDS_REVIEW)->count(),
                    'deprecated' => $group->where('knowledge_status', DovaFaq::KNOWLEDGE_DEPRECATED)->count(),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function agingAnalysis(): array
    {
        $buckets = [
            ['label' => '0–90 يوم', 'min' => 0, 'max' => 90, 'count' => 0],
            ['label' => '91–180 يوم', 'min' => 91, 'max' => 180, 'count' => 0],
            ['label' => '181–365 يوم', 'min' => 181, 'max' => 365, 'count' => 0],
            ['label' => 'أكثر من سنة', 'min' => 366, 'max' => 99999, 'count' => 0],
        ];

        DovaFaq::query()
            ->where('status', DovaFaq::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->get()
            ->each(function (DovaFaq $faq) use (&$buckets) {
                $days = (int) $faq->published_at->diffInDays(now());
                foreach ($buckets as &$bucket) {
                    if ($days >= $bucket['min'] && $days <= $bucket['max']) {
                        $bucket['count']++;
                        break;
                    }
                }
            });

        return $buckets;
    }

    public function scheduleReviewDates(DovaFaq $faq, ?Carbon $reviewedAt = null): void
    {
        $reviewedAt ??= Carbon::now();
        $days = max(1, (int) ($faq->review_frequency_days ?: config('dova-knowledge-governance.default_review_frequency_days', 180)));

        $faq->forceFill([
            'last_reviewed_at' => $reviewedAt,
            'next_review_due_at' => $reviewedAt->copy()->addDays($days),
        ])->save();
    }

    public function completeReview(DovaFaq $faq, ?User $user = null): DovaFaq
    {
        $this->scheduleReviewDates($faq);

        $faq->update([
            'knowledge_status' => DovaFaq::KNOWLEDGE_ACTIVE,
            'updated_by' => $user?->id,
        ]);

        return $faq->fresh(['owner', 'category']);
    }

    public function deprecate(DovaFaq $faq, ?User $user = null): DovaFaq
    {
        $faq->update([
            'knowledge_status' => DovaFaq::KNOWLEDGE_DEPRECATED,
            'updated_by' => $user?->id,
        ]);

        app(DovaKnowledgeSyncService::class)->syncSource('faq');

        return $faq;
    }

    public function markOverdueFaqs(): int
    {
        return DovaFaq::query()
            ->where('status', DovaFaq::STATUS_PUBLISHED)
            ->where('knowledge_status', DovaFaq::KNOWLEDGE_ACTIVE)
            ->whereNotNull('next_review_due_at')
            ->where('next_review_due_at', '<', now())
            ->update(['knowledge_status' => DovaFaq::KNOWLEDGE_NEEDS_REVIEW]);
    }

    public function sendReviewReminders(): int
    {
        $sent = 0;
        $today = now()->startOfDay();
        $beforeDays = config('dova-knowledge-governance.reminder_days_before', 30);
        $overdueDays = config('dova-knowledge-governance.reminder_days_overdue', 30);

        $faqs = DovaFaq::query()
            ->with('owner')
            ->where('status', DovaFaq::STATUS_PUBLISHED)
            ->whereNotNull('owner_user_id')
            ->whereNotNull('next_review_due_at')
            ->whereIn('knowledge_status', [DovaFaq::KNOWLEDGE_ACTIVE, DovaFaq::KNOWLEDGE_NEEDS_REVIEW])
            ->get();

        foreach ($faqs as $faq) {
            $due = $faq->next_review_due_at->startOfDay();
            $type = null;

            if ($due->equalTo($today->copy()->addDays($beforeDays))) {
                $type = 'due_soon';
            } elseif ($due->equalTo($today)) {
                $type = 'due_today';
            } elseif ($due->equalTo($today->copy()->subDays($overdueDays))) {
                $type = 'overdue';
            }

            if ($type === null || ! $faq->owner) {
                continue;
            }

            if ($this->reminderAlreadySent($faq, $type)) {
                continue;
            }

            $faq->owner->notify(new DovaFaqKnowledgeReviewNotification($faq, $type));
            $sent++;
        }

        return $sent;
    }

    protected function reminderAlreadySent(DovaFaq $faq, string $type): bool
    {
        return $faq->owner
            ->notifications()
            ->where('type', DovaFaqKnowledgeReviewNotification::class)
            ->whereDate('created_at', today())
            ->where('data->faq_id', $faq->id)
            ->where('data->reminder_type', $type)
            ->exists();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listOwners(): array
    {
        return User::query()
            ->where('user_type', 'admin')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function reviewFrequencies(): array
    {
        return config('dova-knowledge-governance.review_frequencies', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function formatGovernanceRow(DovaFaq $f): array
    {
        return [
            'id' => $f->id,
            'questionEn' => $f->question_en,
            'category' => $f->category?->name_ar,
            'owner' => $f->owner?->name,
            'ownerId' => $f->owner_user_id,
            'lastReviewed' => $f->last_reviewed_at?->format('Y-m-d') ?? '—',
            'nextReview' => $f->next_review_due_at?->format('Y-m-d') ?? '—',
            'knowledgeStatus' => $f->knowledge_status,
            'knowledgeStatusLabel' => $this->knowledgeStatusLabel($f->knowledge_status),
            'reviewFrequencyDays' => $f->review_frequency_days,
            'isOverdue' => $f->isOverdueForReview() || $f->knowledge_status === DovaFaq::KNOWLEDGE_NEEDS_REVIEW,
            'publishedAt' => $f->published_at?->format('Y-m-d'),
        ];
    }

    public function knowledgeStatusLabel(?string $status): string
    {
        return match ($status) {
            DovaFaq::KNOWLEDGE_ACTIVE => 'نشط',
            DovaFaq::KNOWLEDGE_NEEDS_REVIEW => 'يحتاج مراجعة',
            DovaFaq::KNOWLEDGE_DEPRECATED => 'مهمل',
            DovaFaq::KNOWLEDGE_ARCHIVED => 'مؤرشف',
            default => $status ?? '—',
        };
    }
}
