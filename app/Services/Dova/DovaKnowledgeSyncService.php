<?php

namespace App\Services\Dova;

use App\Models\DovaKnowledgeRecord;
use App\Models\DovaKnowledgeSource;
use App\Services\Website\WebsiteContentService;
use Illuminate\Support\Carbon;

class DovaKnowledgeSyncService
{
    public function __construct(
        protected WebsiteContentService $websiteContent,
        protected DovaKnowledgeIndexBuilder $indexBuilder,
    ) {}

    public function ensureSources(): void
    {
        foreach (config('dova-knowledge.sources', []) as $slug => $meta) {
            DovaKnowledgeSource::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name_en' => $meta['name_en'],
                    'name_ar' => $meta['name_ar'],
                    'enabled' => true,
                    'status' => 'not_indexed',
                ],
            );
        }
    }

    /**
     * @return array{synced: int, records: int, message: string}
     */
    public function syncGroup(string $group): array
    {
        $slugs = config("dova-knowledge.sync_groups.{$group}");

        if ($group === 'everything') {
            $slugs = array_keys(config('dova-knowledge.sources', []));
        }

        if (! is_array($slugs) || $slugs === []) {
            return ['synced' => 0, 'records' => 0, 'message' => 'Unknown sync group.'];
        }

        $totalRecords = 0;
        foreach ($slugs as $slug) {
            $result = $this->syncSource($slug);
            $totalRecords += $result['records'];
        }

        return [
            'synced' => count($slugs),
            'records' => $totalRecords,
            'message' => "Synced {$group} successfully.",
        ];
    }

    /**
     * @return array{records: int, source: string}
     */
    public function syncSource(string $slug): array
    {
        $this->ensureSources();

        $source = DovaKnowledgeSource::query()->where('slug', $slug)->first();
        if ($source === null) {
            return ['records' => 0, 'source' => $slug];
        }

        if (! $source->enabled) {
            return ['records' => 0, 'source' => $slug];
        }

        $raw = $this->websiteContent->isCmsActive()
            ? $this->websiteContent->buildFromDatabase(false)
            : $this->indexBuilder->rawContentFromDefaults();

        $allRecords = $this->indexBuilder->build($raw);

        if ($slug === 'faq') {
            $allRecords = array_merge($allRecords, $this->indexBuilder->buildFromPublishedFaqs());
        }

        $filtered = array_values(array_filter($allRecords, fn ($r) => $r['source_slug'] === $slug));

        $now = Carbon::now();
        $keys = [];

        foreach ($filtered as $row) {
            $keys[] = "{$row['record_key']}:{$row['locale']}";
            DovaKnowledgeRecord::query()->updateOrCreate(
                [
                    'source_slug' => $row['source_slug'],
                    'record_key' => $row['record_key'],
                    'locale' => $row['locale'],
                ],
                [
                    'title' => $row['title'],
                    'content' => $row['content'],
                    'content_updated_at' => $now,
                    'indexed_at' => $now,
                ],
            );
        }

        if ($keys !== []) {
            DovaKnowledgeRecord::query()
                ->where('source_slug', $slug)
                ->get()
                ->each(function (DovaKnowledgeRecord $record) use ($keys) {
                    $compound = "{$record->record_key}:{$record->locale}";
                    if (! in_array($compound, $keys, true)) {
                        $record->delete();
                    }
                });
        } else {
            DovaKnowledgeRecord::query()->where('source_slug', $slug)->delete();
        }

        $count = count($filtered);
        $source->update([
            'record_count' => $count,
            'status' => $count > 0 ? 'indexed' : 'not_indexed',
            'last_synced_at' => $now,
        ]);

        return ['records' => $count, 'source' => $slug];
    }

    /**
     * @return array{synced: int, records: int}
     */
    public function syncAllEnabled(): array
    {
        $this->ensureSources();
        $total = 0;
        $synced = 0;

        foreach (DovaKnowledgeSource::query()->where('enabled', true)->pluck('slug') as $slug) {
            $result = $this->syncSource($slug);
            $total += $result['records'];
            $synced++;
        }

        return ['synced' => $synced, 'records' => $total];
    }
}
