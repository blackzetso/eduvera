<?php

namespace App\Services\Website;

use App\Models\Website\WebsiteLandingPage;
use App\Models\Website\WebsiteLandingSection;
use App\Models\Website\WebsiteLandingSectionRevision;
use App\Models\Website\WebsiteSetting;
use App\Support\Website\WebsiteDefaultsRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebsiteLandingBuilderService
{
    protected function clearLandingCache(): void
    {
        Cache::forget('website.landing.content');
        Cache::forget('website.landing.content.preview');
    }

    public function pageSlug(): string
    {
        return (string) config('website-landing-blocks.page_slug', 'school-talent');
    }

    public function library(): array
    {
        return config('website-landing-blocks.library', []);
    }

    public function customSubtypes(): array
    {
        return config('website-landing-blocks.custom_subtypes', []);
    }

    public function getOrCreatePage(): WebsiteLandingPage
    {
        $page = WebsiteLandingPage::query()->where('slug', $this->pageSlug())->first();

        if ($page) {
            return $page;
        }

        return $this->seedPageFromLegacySettings();
    }

    public function seedPageFromLegacySettings(): WebsiteLandingPage
    {
        return DB::transaction(function () {
            $page = WebsiteLandingPage::query()->create([
                'slug' => $this->pageSlug(),
                'name' => 'School Talent Landing',
                'status' => 'published',
                'published_at' => now(),
            ]);

            $legacy = WebsiteSetting::getValue('landing_sections', []);
            if ($legacy === []) {
                $legacy = WebsiteDefaultsRepository::load()['landingSections'] ?? [];
            }

            $library = $this->library();
            $order = 0;

            foreach ($legacy as $row) {
                $key = $row['key'] ?? null;
                if (! $key || ! isset($library[$key])) {
                    continue;
                }
                $meta = $library[$key];
                WebsiteLandingSection::query()->create([
                    'page_id' => $page->id,
                    'uuid' => (string) Str::uuid(),
                    'block_type' => $key,
                    'admin_name' => $row['label'] ?? $meta['label'],
                    'anchor_id' => $meta['anchor'] ?? null,
                    'sort_order' => $row['sort_order'] ?? (++$order),
                    'is_enabled' => $row['enabled'] ?? true,
                    'is_visible' => true,
                    'settings' => config('website-landing-blocks.default_settings'),
                    'content' => [],
                ]);
            }

            return $page->fresh(['sections']);
        });
    }

    public function sectionsForAdmin(): array
    {
        $page = $this->getOrCreatePage();

        return [
            'page' => $this->pageMeta($page),
            'sections' => $page->sections->map(fn ($s) => $this->sectionForAdmin($s))->values()->all(),
            'library' => $this->libraryCatalog(),
            'customSubtypes' => $this->customSubtypes(),
            'revisions' => $this->recentRevisions($page, 10),
        ];
    }

    public function sectionsForPublic(bool $preview = false): array
    {
        $page = WebsiteLandingPage::query()->where('slug', $this->pageSlug())->first();

        if (! $page) {
            return $this->legacySectionsPayload();
        }

        if (! $preview && $page->status !== 'published') {
            return $this->legacySectionsPayload();
        }

        $now = Carbon::now();

        return $page->sections()
            ->orderBy('sort_order')
            ->get()
            ->filter(function (WebsiteLandingSection $section) use ($preview, $now) {
                if (! $section->is_enabled) {
                    return false;
                }
                if (! $preview && ! $section->is_visible) {
                    return false;
                }
                if ($section->scheduled_starts_at && $now->lt($section->scheduled_starts_at)) {
                    return false;
                }
                if ($section->scheduled_ends_at && $now->gt($section->scheduled_ends_at)) {
                    return false;
                }

                return true;
            })
            ->map(fn ($s) => $s->toPublicArray())
            ->values()
            ->all();
    }

    public function isSectionEnabled(string $blockType): bool
    {
        $page = WebsiteLandingPage::query()->where('slug', $this->pageSlug())->first();

        if ($page) {
            $section = $page->sections()->where('block_type', $blockType)->first();

            return $section
                ? ($section->is_enabled && $section->is_visible)
                : true;
        }

        $legacy = WebsiteSetting::getValue('landing_sections', WebsiteDefaultsRepository::load()['landingSections'] ?? []);
        $row = collect($legacy)->firstWhere('key', $blockType);

        if ($row === null) {
            return true;
        }

        return ($row['enabled'] ?? true) !== false;
    }

    protected function legacySectionsPayload(): array
    {
        $legacy = WebsiteSetting::getValue('landing_sections', WebsiteDefaultsRepository::load()['landingSections'] ?? []);

        return collect($legacy)
            ->filter(fn ($r) => ($r['enabled'] ?? true) !== false)
            ->sortBy('sort_order')
            ->values()
            ->map(fn ($r, $i) => [
                'id' => null,
                'uuid' => 'legacy-'.($r['key'] ?? $i),
                'block_type' => $r['key'],
                'admin_name' => $r['label'] ?? $r['key'],
                'anchor_id' => $this->library()[$r['key']]['anchor'] ?? null,
                'sort_order' => $r['sort_order'] ?? $i,
                'is_enabled' => true,
                'is_visible' => true,
                'settings' => [],
                'content' => [],
                'show_desktop' => true,
                'show_tablet' => true,
                'show_mobile' => true,
                'scheduled_starts_at' => null,
                'scheduled_ends_at' => null,
            ])
            ->all();
    }

    public function addSection(string $blockType, ?string $adminName = null, ?string $customSubtype = null): WebsiteLandingSection
    {
        $library = $this->library();
        if (! isset($library[$blockType])) {
            throw new \InvalidArgumentException("Unknown block type: {$blockType}");
        }

        $page = $this->getOrCreatePage();
        $maxOrder = (int) $page->sections()->max('sort_order');

        $content = [];
        if ($blockType === 'custom' && $customSubtype) {
            $content = ['subtype' => $customSubtype, 'items' => [], 'body' => ''];
        }

        $section = WebsiteLandingSection::query()->create([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'block_type' => $blockType,
            'admin_name' => $adminName ?? $library[$blockType]['label'],
            'anchor_id' => $library[$blockType]['anchor'] ?? null,
            'sort_order' => $maxOrder + 1,
            'is_enabled' => true,
            'is_visible' => true,
            'settings' => config('website-landing-blocks.default_settings'),
            'content' => $content,
        ]);

        $this->clearLandingCache();

        return $section;
    }

    public function duplicateSection(WebsiteLandingSection $section): WebsiteLandingSection
    {
        $copy = $section->replicate(['uuid']);
        $copy->uuid = (string) Str::uuid();
        $copy->admin_name = $section->admin_name.' (Copy)';
        $copy->sort_order = (int) $section->page->sections()->max('sort_order') + 1;
        $copy->duplicated_from_id = $section->id;
        $copy->anchor_id = null;
        $copy->save();

        $this->clearLandingCache();

        return $copy;
    }

    public function reorder(array $orderedUuids): void
    {
        $page = $this->getOrCreatePage();
        $sections = $page->sections()->whereIn('uuid', $orderedUuids)->get()->keyBy('uuid');

        DB::transaction(function () use ($orderedUuids, $sections) {
            foreach ($orderedUuids as $index => $uuid) {
                if ($sections->has($uuid)) {
                    $sections[$uuid]->update(['sort_order' => $index + 1]);
                }
            }
        });

        $this->clearLandingCache();
    }

    public function updateSection(WebsiteLandingSection $section, array $data): WebsiteLandingSection
    {
        $section->fill([
            'admin_name' => $data['admin_name'] ?? $section->admin_name,
            'anchor_id' => $data['anchor_id'] ?? $section->anchor_id,
            'is_enabled' => $data['is_enabled'] ?? $section->is_enabled,
            'is_visible' => $data['is_visible'] ?? $section->is_visible,
            'settings' => $data['settings'] ?? $section->settings,
            'content' => $data['content'] ?? $section->content,
            'show_desktop' => $data['show_desktop'] ?? $section->show_desktop,
            'show_tablet' => $data['show_tablet'] ?? $section->show_tablet,
            'show_mobile' => $data['show_mobile'] ?? $section->show_mobile,
            'scheduled_starts_at' => isset($data['scheduled_starts_at']) ? ($data['scheduled_starts_at'] ?: null) : $section->scheduled_starts_at,
            'scheduled_ends_at' => isset($data['scheduled_ends_at']) ? ($data['scheduled_ends_at'] ?: null) : $section->scheduled_ends_at,
        ]);
        $section->save();

        $this->clearLandingCache();
        app(WebsiteContentService::class)->clearCache();

        return $section->fresh();
    }

    public function deleteSection(WebsiteLandingSection $section): void
    {
        $section->delete();
        $this->clearLandingCache();
    }

    public function setPageStatus(string $status): WebsiteLandingPage
    {
        $page = $this->getOrCreatePage();
        $page->status = $status;
        if ($status === 'published') {
            $page->published_at = now();
        }
        $page->save();

        $this->createRevision($page, 'Status changed to '.$status);
        $this->clearLandingCache();

        return $page->fresh();
    }

    public function publish(): WebsiteLandingPage
    {
        $page = $this->getOrCreatePage();
        $this->createRevision($page, 'Published');
        $page->update(['status' => 'published', 'published_at' => now()]);
        $this->clearLandingCache();

        return $page->fresh();
    }

    public function createRevision(?WebsiteLandingPage $page = null, ?string $note = null): WebsiteLandingSectionRevision
    {
        $page = $page ?? $this->getOrCreatePage();
        $nextVersion = (int) $page->revisions()->max('version') + 1;

        $snapshot = [
            'page' => $this->pageMeta($page),
            'sections' => $page->sections()->orderBy('sort_order')->get()->map(fn ($s) => $this->sectionForAdmin($s))->values()->all(),
        ];

        return WebsiteLandingSectionRevision::query()->create([
            'page_id' => $page->id,
            'version' => $nextVersion,
            'status' => $page->status,
            'snapshot' => $snapshot,
            'note' => $note,
            'created_by' => Auth::id(),
        ]);
    }

    public function restoreRevision(WebsiteLandingSectionRevision $revision): WebsiteLandingPage
    {
        $page = $revision->page;
        $sections = $revision->snapshot['sections'] ?? [];

        DB::transaction(function () use ($page, $sections, $revision) {
            $page->sections()->delete();

            foreach ($sections as $i => $row) {
                WebsiteLandingSection::query()->create([
                    'page_id' => $page->id,
                    'uuid' => $row['uuid'] ?? (string) Str::uuid(),
                    'block_type' => $row['block_type'],
                    'admin_name' => $row['admin_name'],
                    'anchor_id' => $row['anchor_id'] ?? null,
                    'sort_order' => $row['sort_order'] ?? ($i + 1),
                    'is_enabled' => $row['is_enabled'] ?? true,
                    'is_visible' => $row['is_visible'] ?? true,
                    'settings' => $row['settings'] ?? [],
                    'content' => $row['content'] ?? [],
                    'show_desktop' => $row['show_desktop'] ?? true,
                    'show_tablet' => $row['show_tablet'] ?? true,
                    'show_mobile' => $row['show_mobile'] ?? true,
                    'scheduled_starts_at' => $row['scheduled_starts_at'] ?? null,
                    'scheduled_ends_at' => $row['scheduled_ends_at'] ?? null,
                ]);
            }

            if (! empty($revision->snapshot['page']['status'])) {
                $page->status = $revision->snapshot['page']['status'];
                $page->save();
            }
        });

        $this->createRevision($page->fresh(['sections']), 'Restored from v'.$revision->version);
        $this->clearLandingCache();

        return $page->fresh(['sections']);
    }

    protected function pageMeta(WebsiteLandingPage $page): array
    {
        return [
            'id' => $page->id,
            'slug' => $page->slug,
            'name' => $page->name,
            'status' => $page->status,
            'published_at' => $page->published_at?->toIso8601String(),
        ];
    }

    protected function sectionForAdmin(WebsiteLandingSection $section): array
    {
        $meta = $this->library()[$section->block_type] ?? ['label' => $section->block_type];

        return array_merge($section->toPublicArray(), [
            'library_label' => $meta['label'] ?? $section->block_type,
            'is_custom' => ($section->block_type === 'custom'),
        ]);
    }

    protected function libraryCatalog(): array
    {
        return collect($this->library())
            ->map(fn ($meta, $key) => array_merge(['key' => $key], $meta))
            ->values()
            ->all();
    }

    protected function recentRevisions(WebsiteLandingPage $page, int $limit): array
    {
        return $page->revisions()
            ->orderByDesc('version')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'version' => $r->version,
                'status' => $r->status,
                'note' => $r->note,
                'created_at' => $r->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
