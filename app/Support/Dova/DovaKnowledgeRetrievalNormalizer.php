<?php

namespace App\Support\Dova;

class DovaKnowledgeRetrievalNormalizer
{
    /**
     * @return array{
     *   original: string,
     *   normalized: string,
     *   language: string,
     *   expanded: string,
     *   search_terms: array<int, string>,
     *   intent: array{intent: string, confidence: float}|null
     * }
     */
    public function analyze(string $message): array
    {
        $normalized = $this->normalize($message);
        $language = $this->detectLanguage($message);
        $expanded = $this->expandSynonyms($normalized);
        $searchTerms = $this->buildSearchTerms($normalized, $expanded);

        return [
            'original' => $message,
            'normalized' => $normalized,
            'language' => $language,
            'expanded' => $expanded,
            'search_terms' => $searchTerms,
            'intent' => $this->resolveConceptIntent($normalized),
        ];
    }

    public function normalize(string $message): string
    {
        $message = trim($message);
        $message = preg_replace('/[؟?!.،,:;]+/u', '', $message);
        $message = preg_replace('/\s+/u', ' ', $message);
        $message = mb_strtolower($message);

        if ($this->containsArabic($message)) {
            $message = $this->normalizeArabicScript($message);
        }

        return trim($message);
    }

    public function detectLanguage(string $message): string
    {
        $hasArabic = $this->containsArabic($message);
        $hasLatin = (bool) preg_match('/[a-zA-Z]/', $message);

        if ($hasArabic && $hasLatin) {
            return 'mixed';
        }

        if ($hasArabic) {
            return 'ar';
        }

        return 'en';
    }

    /**
     * @return array{intent: string, confidence: float}|null
     */
    public function resolveConceptIntent(string $normalized): ?array
    {
        if ($normalized === '') {
            return null;
        }

        $concepts = config('dova-knowledge-retrieval.concepts', []);
        $best = null;
        $bestScore = 0.0;

        foreach ($concepts as $intent => $definition) {
            $score = $this->scoreConcept($normalized, $definition);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [
                    'intent' => $intent,
                    'confidence' => (float) ($definition['confidence'] ?? 0.9) * $score,
                ];
            }
        }

        if ($best === null || $bestScore < 0.72) {
            if (preg_match('/^(what is|what are|who is|where is|when do|tell me about)\b/u', $normalized)) {
                return ['intent' => 'general_fact', 'confidence' => 0.72];
            }

            return null;
        }

        $best['confidence'] = min(0.99, round($best['confidence'], 2));

        return $best;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function scoreConcept(string $normalized, array $definition): float
    {
        $longestPhrase = 0;
        foreach ($definition['phrases'] ?? [] as $phrase) {
            $phraseNorm = $this->normalize((string) $phrase);
            if ($phraseNorm === '') {
                continue;
            }

            if ($normalized === $phraseNorm || str_contains($normalized, $phraseNorm)) {
                $longestPhrase = max($longestPhrase, mb_strlen($phraseNorm));
            }
        }

        $phraseScore = $longestPhrase > 0
            ? min(1.0, 0.82 + ($longestPhrase / 40))
            : 0.0;

        $groupScore = $this->scoreTokenGroups($normalized, $definition['token_groups'] ?? []);

        if ($phraseScore > 0) {
            return $phraseScore;
        }

        if ($groupScore >= 1.0) {
            return 0.9;
        }

        if ($groupScore >= 0.5) {
            return $groupScore * 0.82;
        }

        return 0.0;
    }

    /**
     * @param  array<int, array<int, string>>  $groups
     */
    protected function scoreTokenGroups(string $normalized, array $groups): float
    {
        if ($groups === []) {
            return 0.0;
        }

        $matchedGroups = 0;
        foreach ($groups as $group) {
            foreach ($group as $token) {
                $tokenNorm = $this->normalize((string) $token);
                if ($tokenNorm !== '' && str_contains($normalized, $tokenNorm)) {
                    $matchedGroups++;
                    break;
                }
            }
        }

        return $matchedGroups / count($groups);
    }

    public function expandSynonyms(string $normalized): string
    {
        $terms = [$normalized];
        $synonyms = config('dova-knowledge-retrieval.synonyms', []);

        foreach ($synonyms as $group) {
            $hit = false;
            foreach ($group as $term) {
                $termNorm = $this->normalize($term);
                if ($termNorm !== '' && str_contains($normalized, $termNorm)) {
                    $hit = true;
                    break;
                }
            }

            if ($hit) {
                foreach ($group as $term) {
                    $terms[] = $this->normalize($term);
                }
            }
        }

        return implode(' ', array_values(array_unique(array_filter($terms))));
    }

    /**
     * @return array<int, string>
     */
    protected function buildSearchTerms(string $normalized, string $expanded): array
    {
        $terms = array_values(array_unique(array_filter([
            $normalized,
            $expanded,
            ...preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [],
            ...preg_split('/\s+/u', $expanded, -1, PREG_SPLIT_NO_EMPTY) ?: [],
        ])));

        return array_values(array_filter($terms, fn ($t) => mb_strlen($t) > 1));
    }

    protected function containsArabic(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}]/u', $text);
    }

    protected function normalizeArabicScript(string $text): string
    {
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text);
        $text = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $text);
        $text = str_replace(['ى'], 'ي', $text);
        $text = str_replace(['ة'], 'ه', $text);

        return $text;
    }
}
