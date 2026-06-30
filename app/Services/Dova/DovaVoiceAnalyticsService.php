<?php

namespace App\Services\Dova;

use App\Models\DovaKnowledgeQuery;
use App\Models\DovaVoiceRecognition;
use Illuminate\Support\Facades\DB;

class DovaVoiceAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $voiceQuestions = (int) DovaKnowledgeQuery::query()->where('input_method', 'voice')->count();
        $textQuestions = (int) DovaKnowledgeQuery::query()->where('input_method', 'text')->count();

        $totalRecognitions = (int) DovaVoiceRecognition::query()->count();
        $successfulRecognitions = (int) DovaVoiceRecognition::query()->where('success', true)->count();
        $recognitionSuccessRate = $totalRecognitions > 0
            ? (int) round(($successfulRecognitions / $totalRecognitions) * 100)
            : 0;

        $languageBreakdown = DovaKnowledgeQuery::query()
            ->where('input_method', 'voice')
            ->whereNotNull('detected_language')
            ->select('detected_language', DB::raw('COUNT(*) as total'))
            ->groupBy('detected_language')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'language' => $row->detected_language,
                'count' => (int) $row->total,
            ])
            ->values()
            ->all();

        $engineBreakdown = DovaVoiceRecognition::query()
            ->select('engine', DB::raw('COUNT(*) as total'))
            ->groupBy('engine')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'engine' => $row->engine,
                'count' => (int) $row->total,
            ])
            ->values()
            ->all();

        $failedRecognitions = $totalRecognitions - $successfulRecognitions;
        $recognitionFailureRate = $totalRecognitions > 0
            ? (int) round(($failedRecognitions / $totalRecognitions) * 100)
            : 0;

        $avgDurationMs = (int) round((float) (DovaVoiceRecognition::query()
            ->where('success', true)
            ->whereNotNull('duration_ms')
            ->avg('duration_ms') ?? 0));

        $mostCommonVoiceQuestions = DovaKnowledgeQuery::query()
            ->where('input_method', 'voice')
            ->select('normalized_question', DB::raw('MAX(question) as question'), DB::raw('COUNT(*) as total'))
            ->whereNotNull('normalized_question')
            ->groupBy('normalized_question')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'question' => $row->question,
                'count' => (int) $row->total,
            ])
            ->values()
            ->all();

        $arabicVoice = (int) DovaKnowledgeQuery::query()
            ->where('input_method', 'voice')
            ->where('detected_language', 'ar')
            ->count();

        $englishVoice = (int) DovaKnowledgeQuery::query()
            ->where('input_method', 'voice')
            ->where('detected_language', 'en')
            ->count();

        $voiceWithLanguage = max(1, $arabicVoice + $englishVoice);

        return [
            'voiceQuestions' => $voiceQuestions,
            'textQuestions' => $textQuestions,
            'totalRecognitions' => $totalRecognitions,
            'successfulRecognitions' => $successfulRecognitions,
            'recognitionSuccessRate' => $recognitionSuccessRate,
            'recognitionFailureRate' => $recognitionFailureRate,
            'averageRecordingDurationMs' => $avgDurationMs,
            'averageRecordingDurationLabel' => $this->formatDuration($avgDurationMs),
            'mostCommonVoiceQuestions' => $mostCommonVoiceQuestions,
            'languageBreakdown' => $languageBreakdown,
            'engineBreakdown' => $engineBreakdown,
            'arabicVsEnglish' => [
                'arabic' => $arabicVoice,
                'english' => $englishVoice,
                'arabicPercent' => (int) round(($arabicVoice / $voiceWithLanguage) * 100),
                'englishPercent' => (int) round(($englishVoice / $voiceWithLanguage) * 100),
            ],
        ];
    }

    protected function formatDuration(int $ms): string
    {
        if ($ms <= 0) {
            return '0s';
        }

        $seconds = (int) round($ms / 1000);

        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = intdiv($seconds, 60);
        $remainder = $seconds % 60;

        return $remainder > 0 ? "{$minutes}m {$remainder}s" : "{$minutes}m";
    }

    public function logRecognition(
        bool $success,
        string $engine,
        array $context,
        ?string $transcript = null,
        ?string $detectedLanguage = null,
        ?string $errorCode = null,
        ?int $durationMs = null,
        ?int $userId = null,
    ): DovaVoiceRecognition {
        return DovaVoiceRecognition::query()->create([
            'user_id' => $userId,
            'portal' => (string) ($context['portal'] ?? 'public'),
            'role' => (string) ($context['role'] ?? 'guest'),
            'success' => $success,
            'engine' => $engine,
            'detected_language' => $detectedLanguage,
            'transcript' => $transcript !== null && $transcript !== '' ? mb_substr($transcript, 0, 2000) : null,
            'error_code' => $errorCode,
            'duration_ms' => $durationMs,
        ]);
    }
}
