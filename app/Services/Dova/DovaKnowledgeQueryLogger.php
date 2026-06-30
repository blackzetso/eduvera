<?php

namespace App\Services\Dova;

use App\Models\DovaKnowledgeQuery;

class DovaKnowledgeQueryLogger
{
    public function log(
        string $question,
        array $context,
        bool $answered,
        ?string $intent = null,
        ?string $sourceSlug = null,
        ?string $recordKey = null,
        ?float $confidence = null,
        ?int $responseMs = null,
        ?string $matchedContent = null,
        ?string $answerPreview = null,
        ?int $userId = null,
        string $inputMethod = 'text',
        ?string $detectedLanguage = null,
    ): DovaKnowledgeQuery {
        $normalized = $this->normalize($question);

        $query = DovaKnowledgeQuery::query()->create([
            'question' => $question,
            'normalized_question' => $normalized,
            'portal' => (string) ($context['portal'] ?? 'public'),
            'role' => (string) ($context['role'] ?? 'guest'),
            'input_method' => in_array($inputMethod, ['text', 'voice'], true) ? $inputMethod : 'text',
            'detected_language' => $detectedLanguage,
            'user_id' => $userId,
            'answered' => $answered,
            'intent' => $intent,
            'source_slug' => $sourceSlug,
            'record_key' => $recordKey,
            'confidence' => $confidence,
            'response_ms' => $responseMs,
            'matched_content' => $matchedContent,
            'answer_preview' => $answerPreview,
        ]);

        if (! $answered) {
            try {
                app(DovaKnowledgeGapDetectionService::class)->processUnansweredQuery(
                    $question,
                    $normalized,
                    (string) ($context['portal'] ?? 'public'),
                    (string) ($context['role'] ?? 'guest'),
                );
            } catch (\Throwable) {
                // Gap detection must not break query logging.
            }
        }

        return $query;
    }

    protected function normalize(string $question): string
    {
        $question = trim($question);
        $question = preg_replace('/[؟?!.،,:;]+/u', '', $question);
        $question = preg_replace('/\s+/u', ' ', $question);

        return mb_strtolower($question);
    }
}
