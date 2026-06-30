<?php

namespace App\Services\Dova;

use App\Models\DovaAiUsageLog;
use App\Models\DovaKnowledgeQuery;
use Illuminate\Support\Facades\DB;

class DovaAiAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $total = (int) DovaAiUsageLog::query()->count();
        $successful = (int) DovaAiUsageLog::query()->where('success', true)->count();
        $fallbacks = (int) DovaAiUsageLog::query()->where('used_fallback', true)->count();

        $avgTokens = (int) round((float) DovaAiUsageLog::query()->avg('total_tokens'));
        $avgResponseMs = (int) round((float) DovaAiUsageLog::query()->avg('response_ms'));
        $totalCost = (float) DovaAiUsageLog::query()->sum('estimated_cost');

        $byType = DovaAiUsageLog::query()
            ->select('request_type', DB::raw('COUNT(*) as total'))
            ->groupBy('request_type')
            ->pluck('total', 'request_type')
            ->all();

        $trend = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i)->startOfDay();
            $trend[] = [
                'date' => $day->format('M d'),
                'requests' => DovaAiUsageLog::query()->whereDate('created_at', $day)->count(),
                'cost' => round((float) DovaAiUsageLog::query()->whereDate('created_at', $day)->sum('estimated_cost'), 4),
            ];
        }

        $topTopics = DovaKnowledgeQuery::query()
            ->select('normalized_question', DB::raw('MAX(question) as question'), DB::raw('COUNT(*) as total'))
            ->groupBy('normalized_question')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['question' => $r->question, 'count' => (int) $r->total])
            ->all();

        return [
            'aiEnabled' => app(DovaLLMService::class)->isEnabled(),
            'model' => config('dova-ai.model'),
            'totalRequests' => $total,
            'successfulRequests' => $successful,
            'fallbackRequests' => $fallbacks,
            'averageTokens' => $avgTokens,
            'averageResponseMs' => $avgResponseMs,
            'estimatedCost' => round($totalCost, 4),
            'byType' => $byType,
            'trend' => $trend,
            'topTopics' => $topTopics,
        ];
    }
}
