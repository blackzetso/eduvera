<?php

namespace App\Services\Dova;

use App\Models\DovaKnowledgeGap;
use App\Models\DovaKnowledgeQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DovaKnowledgeGapDetectionService
{
    public function __construct(
        protected DovaFaqCategoryService $categories,
    ) {}

    public function processUnansweredQuery(
        string $question,
        string $normalizedQuestion,
        string $portal = 'public',
        string $role = 'guest',
    ): void {
        if ($normalizedQuestion === '') {
            return;
        }

        $this->categories->ensureDefaults();

        $match = $this->findSimilarGap($normalizedQuestion);

        if ($match !== null) {
            $this->mergeIntoGap($match, $question, $portal, $role);

            return;
        }

        $topic = $this->deriveTopic($question);
        $slug = $this->uniqueSlug($topic);

        $now = Carbon::now();

        DovaKnowledgeGap::query()->create([
            'topic' => $topic,
            'topic_slug' => $slug,
            'frequency' => 1,
            'first_asked_at' => $now,
            'last_asked_at' => $now,
            'portal' => $portal,
            'role' => $role,
            'suggested_category' => $this->suggestCategory($question),
            'status' => DovaKnowledgeGap::STATUS_OPEN,
            'priority' => 'low',
            'sample_questions' => [$question],
        ]);
    }

    public function syncFromQueryLogs(): int
    {
        $groups = DovaKnowledgeQuery::query()
            ->where('answered', false)
            ->whereNotNull('normalized_question')
            ->selectRaw('normalized_question, MAX(question) as question, COUNT(*) as frequency, MAX(created_at) as last_asked, MAX(portal) as portal, MAX(role) as role')
            ->groupBy('normalized_question')
            ->get();

        $processed = 0;
        foreach ($groups as $group) {
            $existing = $this->findSimilarGap($group->normalized_question);
            if ($existing !== null) {
                $firstAsked = DovaKnowledgeQuery::query()
                    ->where('answered', false)
                    ->where('normalized_question', $group->normalized_question)
                    ->min('created_at');

                $existing->update([
                    'frequency' => max($existing->frequency, (int) $group->frequency),
                    'first_asked_at' => $existing->first_asked_at ?? ($firstAsked ? Carbon::parse($firstAsked) : null),
                    'last_asked_at' => Carbon::parse($group->last_asked),
                ]);
            } else {
                $this->processUnansweredQuery(
                    $group->question,
                    $group->normalized_question,
                    $group->portal ?? 'public',
                    $group->role ?? 'guest',
                );
            }
            $processed++;
        }

        $this->recalculatePriorities();

        return $processed;
    }

    public function recalculatePriorities(): void
    {
        $thresholds = config('dova-knowledge.gap_priorities', ['high' => 30, 'medium' => 10, 'low' => 3]);

        DovaKnowledgeGap::query()
            ->whereIn('status', [DovaKnowledgeGap::STATUS_OPEN, DovaKnowledgeGap::STATUS_IN_PROGRESS])
            ->each(function (DovaKnowledgeGap $gap) use ($thresholds) {
                $priority = 'low';
                if ($gap->frequency >= ($thresholds['high'] ?? 30)) {
                    $priority = 'high';
                } elseif ($gap->frequency >= ($thresholds['medium'] ?? 10)) {
                    $priority = 'medium';
                }
                $gap->update(['priority' => $priority]);
            });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listGaps(array $filters = [], int $limit = 100): array
    {
        $query = DovaKnowledgeGap::query()
            ->whereIn('status', [
                DovaKnowledgeGap::STATUS_OPEN,
                DovaKnowledgeGap::STATUS_IN_PROGRESS,
            ])
            ->orderByDesc('frequency');

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['portal'])) {
            $query->where('portal', $filters['portal']);
        }

        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (! empty($filters['category'])) {
            $query->where('suggested_category', $filters['category']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->limit($limit)->get()->map(fn (DovaKnowledgeGap $g) => [
            'id' => $g->id,
            'topic' => $g->topic,
            'frequency' => $g->frequency,
            'lastAsked' => $g->last_asked_at?->format('Y-m-d'),
            'portal' => $g->portal,
            'role' => $g->role,
            'suggestedCategory' => $g->suggested_category,
            'status' => $g->status,
            'priority' => $g->priority,
            'sampleQuestions' => $g->sample_questions ?? [],
            'hasFaq' => $g->faq()->exists(),
        ])->all();
    }

    protected function findSimilarGap(string $normalizedQuestion): ?DovaKnowledgeGap
    {
        $gaps = DovaKnowledgeGap::query()
            ->whereIn('status', [
                DovaKnowledgeGap::STATUS_OPEN,
                DovaKnowledgeGap::STATUS_IN_PROGRESS,
            ])
            ->get();

        $best = null;
        $bestScore = 0.0;

        foreach ($gaps as $gap) {
            $samples = $gap->sample_questions ?? [];
            foreach ($samples as $sample) {
                $score = $this->similarityScore($normalizedQuestion, $this->normalize($sample));
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $gap;
                }
            }
        }

        return $bestScore >= 0.55 ? $best : null;
    }

    protected function mergeIntoGap(DovaKnowledgeGap $gap, string $question, string $portal, string $role): void
    {
        $samples = $gap->sample_questions ?? [];
        if (! in_array($question, $samples, true)) {
            $samples[] = $question;
        }

        $gap->update([
            'frequency' => $gap->frequency + 1,
            'last_asked_at' => Carbon::now(),
            'portal' => $portal,
            'role' => $role,
            'sample_questions' => array_slice($samples, -10),
        ]);

        $this->recalculatePriorities();
    }

    protected function deriveTopic(string $question): string
    {
        $category = $this->suggestCategory($question);
        $categories = collect(config('dova-knowledge.faq_categories', []));
        $match = $categories->firstWhere('slug', $category);

        if ($match) {
            return $match['name_en'];
        }

        $clean = preg_replace('/^(what|how|when|where|who|why|is|are|do|does|can|could)\s+(is|are|the|a|an)?\s*/i', '', trim($question));
        $clean = preg_replace('/[؟?!.]+$/u', '', $clean ?? '');
        $clean = trim($clean ?? $question);

        return Str::title(Str::limit($clean, 60, ''));
    }

    protected function suggestCategory(string $question): string
    {
        $q = mb_strtolower($question);

        $rules = [
            'fees' => ['fee', 'fees', 'tuition', 'cost', 'price', 'رسوم', 'مصاريف', 'تكلفة'],
            'admissions' => ['admission', 'admit', 'apply', 'enroll', 'قبول', 'تقديم', 'تسجيل'],
            'transportation' => ['bus', 'transport', 'route', 'باص', 'مواصلات', 'حافلة'],
            'uniform' => ['uniform', 'dress', 'زي', 'ملابس'],
            'programs' => ['program', 'curriculum', 'stage', 'برنامج', 'مرحلة', 'منهج'],
            'attendance' => ['attendance', 'absent', 'حضور', 'غياب'],
            'policies' => ['policy', 'rule', 'سياسة', 'قانون'],
            'parents' => ['parent', 'guardian', 'ولي', 'أولياء'],
            'teachers' => ['teacher', 'staff', 'مدرس', 'معلم'],
            'student_life' => ['student life', 'activity', 'club', 'أنشطة', 'نوادي'],
        ];

        foreach ($rules as $slug => $terms) {
            foreach ($terms as $term) {
                if (str_contains($q, $term)) {
                    return $slug;
                }
            }
        }

        return 'general';
    }

    protected function uniqueSlug(string $topic): string
    {
        $base = Str::slug($topic) ?: 'gap-'.time();
        $slug = $base;
        $i = 1;

        while (DovaKnowledgeGap::query()->where('topic_slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    protected function normalize(string $message): string
    {
        $message = trim($message);
        $message = preg_replace('/[؟?!.،,:;]+/u', '', $message);
        $message = preg_replace('/\s+/u', ' ', $message);

        return mb_strtolower($message);
    }

    protected function similarityScore(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b || str_contains($a, $b) || str_contains($b, $a)) {
            return 1.0;
        }

        $tokensA = array_values(array_filter(explode(' ', $a), fn ($t) => mb_strlen($t) > 2));
        if ($tokensA === []) {
            return 0.0;
        }

        $hits = 0;
        foreach ($tokensA as $token) {
            if (str_contains($b, $token)) {
                $hits++;
            }
        }

        return $hits / count($tokensA);
    }
}
