<?php

namespace App\Services\Dova;

use App\Models\DovaKnowledgeQuery;
use App\Models\DovaKnowledgeRecord;
use App\Models\DovaKnowledgeSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DovaKnowledgeAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboardOverview(): array
    {
        $this->ensureSourcesCounted();

        $sources = DovaKnowledgeSource::query()->get();
        $totalRecords = (int) DovaKnowledgeRecord::query()->count();
        $lastSync = $sources->max('last_synced_at');

        $totalAsked = (int) DovaKnowledgeQuery::query()->count();
        $totalAnswered = (int) DovaKnowledgeQuery::query()->where('answered', true)->count();
        $totalUnanswered = $totalAsked - $totalAnswered;

        $voiceStats = app(DovaVoiceAnalyticsService::class)->summary();

        return [
            'totalSources' => $sources->count(),
            'enabledSources' => $sources->where('enabled', true)->count(),
            'indexedSources' => $sources->where('status', 'indexed')->count(),
            'totalRecords' => $totalRecords,
            'lastSyncDate' => $lastSync?->toIso8601String(),
            'lastSyncLabel' => $lastSync?->diffForHumans() ?? 'Never',
            'healthScore' => $this->healthScore(),
            'totalQuestionsAsked' => $totalAsked,
            'totalAnswered' => $totalAnswered,
            'totalUnanswered' => max(0, $totalUnanswered),
            'voiceQuestions' => $voiceStats['voiceQuestions'],
            'textQuestions' => $voiceStats['textQuestions'],
            'recognitionSuccessRate' => $voiceStats['recognitionSuccessRate'],
        ];
    }

    public function healthScore(): int
    {
        $sources = DovaKnowledgeSource::query()->where('enabled', true)->get();
        $enabledCount = max(1, $sources->count());
        $indexedRatio = $sources->where('status', 'indexed')->count() / $enabledCount;

        $totalAsked = (int) DovaKnowledgeQuery::query()->count();
        $successRate = $totalAsked > 0
            ? DovaKnowledgeQuery::query()->where('answered', true)->count() / $totalAsked
            : 0.5;

        $lastSync = $sources->max('last_synced_at');
        $freshness = 1.0;
        if ($lastSync instanceof Carbon) {
            $days = $lastSync->diffInDays(now());
            $freshness = max(0, 1 - min($days, 30) / 30);
        } else {
            $freshness = 0.2;
        }

        $unanswered = (int) DovaKnowledgeQuery::query()->where('answered', false)->count();
        $unansweredPenalty = $totalAsked > 0
            ? max(0, 1 - min($unanswered / max($totalAsked, 1), 1))
            : 0.8;

        $score = ($indexedRatio * 25)
            + ($successRate * 35)
            + ($freshness * 20)
            + ($unansweredPenalty * 20);

        return (int) round(min(100, max(0, $score)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sourcesList(): array
    {
        return DovaKnowledgeSource::query()
            ->orderBy('slug')
            ->get()
            ->map(fn (DovaKnowledgeSource $s) => [
                'id' => $s->id,
                'slug' => $s->slug,
                'name' => $s->name_ar,
                'nameEn' => $s->name_en,
                'enabled' => $s->enabled,
                'status' => $s->status,
                'statusLabel' => $s->status === 'indexed' ? 'مفهرس' : 'غير مفهرس',
                'recordCount' => $s->record_count,
                'lastSyncedAt' => $s->last_synced_at?->toIso8601String(),
                'lastSyncedLabel' => $s->last_synced_at?->format('Y-m-d H:i') ?? '—',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function unansweredQuestions(int $limit = 50): array
    {
        return DovaKnowledgeQuery::query()
            ->select('normalized_question', DB::raw('MAX(question) as question'), DB::raw('COUNT(*) as frequency'), DB::raw('MAX(created_at) as last_asked'), DB::raw('MAX(portal) as portal'), DB::raw('MAX(role) as role'))
            ->where('answered', false)
            ->whereNotNull('normalized_question')
            ->groupBy('normalized_question')
            ->orderByDesc('frequency')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'question' => $row->question,
                'frequency' => (int) $row->frequency,
                'date' => Carbon::parse($row->last_asked)->format('Y-m-d'),
                'portal' => $row->portal,
                'role' => $row->role,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function knowledgeGaps(int $limit = 20): array
    {
        return collect($this->unansweredQuestions($limit))
            ->map(fn ($row) => [
                'topic' => $row['question'],
                'frequency' => $row['frequency'],
                'suggestedSource' => $this->suggestSource($row['question']),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function chartData(): array
    {
        $thirtyDaysAgo = now()->subDays(29)->startOfDay();

        $unansweredTrend = [];
        for ($i = 0; $i < 30; $i++) {
            $day = $thirtyDaysAgo->copy()->addDays($i);
            $count = DovaKnowledgeQuery::query()
                ->where('answered', false)
                ->whereDate('created_at', $day)
                ->count();
            $unansweredTrend[] = [
                'date' => $day->format('M d'),
                'count' => $count,
            ];
        }

        $topSources = DovaKnowledgeQuery::query()
            ->where('answered', true)
            ->whereNotNull('source_slug')
            ->select('source_slug', DB::raw('COUNT(*) as total'))
            ->groupBy('source_slug')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($r) => [
                'source' => $this->sourceLabel($r->source_slug),
                'count' => (int) $r->total,
            ])
            ->all();

        $topQuestions = DovaKnowledgeQuery::query()
            ->select('normalized_question', DB::raw('MAX(question) as question'), DB::raw('COUNT(*) as total'))
            ->groupBy('normalized_question')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'question' => $r->question,
                'count' => (int) $r->total,
            ])
            ->all();

        $topicBuckets = $this->topicBuckets();

        $totalAsked = (int) DovaKnowledgeQuery::query()->count();
        $answered = (int) DovaKnowledgeQuery::query()->where('answered', true)->count();
        $answerCoverage = $totalAsked > 0 ? (int) round(($answered / $totalAsked) * 100) : 0;

        $topMissing = app(DovaFaqAnalyticsService::class)->topMissingTopics(8);

        $voiceStats = app(DovaVoiceAnalyticsService::class)->summary();

        return [
            'mostAskedTopics' => $topicBuckets,
            'mostUsedSources' => $topSources,
            'unansweredTrend' => $unansweredTrend,
            'topQuestions' => $topQuestions,
            'knowledgeCoverage' => $answerCoverage,
            'topMissingTopics' => $topMissing,
            'totalAsked' => $totalAsked,
            'totalAnswered' => $answered,
            'voice' => $voiceStats,
        ];
    }

    /**
     * @return array<int, array{topic: string, count: int}>
     */
    protected function topicBuckets(): array
    {
        $keywords = [
            'Admissions' => ['admission', 'apply', 'قبول', 'تقديم'],
            'School Info' => ['school', 'name', 'address', 'مدرسة', 'عنوان'],
            'Fees' => ['fee', 'tuition', 'رسوم', 'مصاريف'],
            'Programs' => ['program', 'curriculum', 'برنامج', 'مرحلة'],
            'Events' => ['event', 'فعالية', 'calendar'],
            'Contact' => ['phone', 'email', 'contact', 'هاتف', 'تواصل'],
        ];

        $results = [];
        foreach ($keywords as $label => $terms) {
            $query = DovaKnowledgeQuery::query();
            $query->where(function ($q) use ($terms) {
                foreach ($terms as $term) {
                    $q->orWhere('normalized_question', 'like', "%{$term}%");
                }
            });
            $count = $query->count();
            if ($count > 0) {
                $results[] = ['topic' => $label, 'count' => $count];
            }
        }

        usort($results, fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_slice($results, 0, 8);
    }

    protected function suggestSource(string $question): string
    {
        $q = mb_strtolower($question);

        $rules = [
            'admissions' => ['admission', 'apply', 'age', 'قبول', 'تقديم', 'عمر'],
            'faq' => ['how', 'what', 'why', 'كيف', 'ماذا'],
            'events' => ['event', 'calendar', 'فعالية'],
            'news' => ['news', 'أخبار'],
            'policies' => ['policy', 'document', 'سياسة', 'مستند'],
            'contact' => ['phone', 'email', 'address', 'هاتف', 'عنوان'],
            'school_info' => ['school', 'name', 'مدرسة', 'اسم'],
            'academic_programs' => ['program', 'curriculum', 'برنامج'],
        ];

        foreach ($rules as $source => $terms) {
            foreach ($terms as $term) {
                if (str_contains($q, $term)) {
                    return $this->sourceLabel($source);
                }
            }
        }

        return 'FAQ';
    }

    protected function sourceLabel(string $slug): string
    {
        $dot = strpos($slug, '.');
        $base = $dot !== false ? substr($slug, 0, $dot) : $slug;

        $map = [
            'nav_links' => 'navigation',
            'faqs' => 'faq',
            'school_info' => 'school_info',
        ];
        $base = $map[$base] ?? $base;

        return config("dova-knowledge.sources.{$base}.name_en")
            ?? config("dova-knowledge.sources.{$slug}.name_en")
            ?? $slug;
    }

    protected function ensureSourcesCounted(): void
    {
        if (DovaKnowledgeSource::query()->exists()) {
            return;
        }

        app(DovaKnowledgeSyncService::class)->ensureSources();
    }
}
