<?php

namespace App\Services\Dova;

use App\Models\DovaKnowledgeRecord;

class DovaKnowledgeExplorerService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, int $limit = 25): array
    {
        $needle = trim($query);
        if ($needle === '') {
            return [];
        }

        $like = '%'.$needle.'%';

        $records = DovaKnowledgeRecord::query()
            ->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('content', 'like', $like);
            })
            ->orderByDesc('indexed_at')
            ->limit(100)
            ->get();

        return $records
            ->map(function (DovaKnowledgeRecord $record) use ($needle) {
                $confidence = $this->confidence($needle, $record->content, $record->title ?? '');

                return [
                    'id' => $record->id,
                    'source' => $this->sourceLabel($record->source_slug),
                    'sourceSlug' => $record->source_slug,
                    'recordKey' => $record->record_key,
                    'title' => $record->title,
                    'content' => $record->content,
                    'locale' => $record->locale,
                    'lastUpdated' => $record->content_updated_at?->format('Y-m-d H:i')
                        ?? $record->indexed_at?->format('Y-m-d H:i')
                        ?? '—',
                    'confidence' => round($confidence, 2),
                ];
            })
            ->sortByDesc('confidence')
            ->take($limit)
            ->values()
            ->all();
    }

    protected function confidence(string $query, string $content, string $title): float
    {
        $haystack = mb_strtolower($title.' '.$content);
        $needle = mb_strtolower($query);

        if ($haystack === '' || $needle === '') {
            return 0.0;
        }

        if (str_contains($haystack, $needle)) {
            return 0.95;
        }

        $tokens = array_values(array_filter(explode(' ', $needle), fn ($t) => mb_strlen($t) > 2));
        if ($tokens === []) {
            return 0.3;
        }

        $hits = 0;
        foreach ($tokens as $token) {
            if (str_contains($haystack, $token)) {
                $hits++;
            }
        }

        return min(0.9, 0.4 + ($hits / count($tokens)) * 0.5);
    }

    protected function sourceLabel(string $slug): string
    {
        return config("dova-knowledge.sources.{$slug}.name_en") ?? $slug;
    }
}
