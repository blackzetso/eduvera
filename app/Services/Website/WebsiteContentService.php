<?php

namespace App\Services\Website;

use App\Models\Website\WebsiteAnnouncement;
use App\Models\Website\WebsiteCareer;
use App\Models\Website\WebsiteEvent;
use App\Models\Website\WebsiteFacility;
use App\Models\Website\WebsiteGalleryItem;
use App\Models\Website\WebsiteNavLink;
use App\Models\Website\WebsitePost;
use App\Models\Website\WebsiteSetting;
use App\Models\Website\WebsiteStage;
use App\Models\Website\WebsiteSuccessStory;
use App\Models\Website\WebsiteTestimonial;
use App\Support\LocalizedContent;
use App\Support\Website\WebsiteDefaultsRepository;
use App\Support\Website\WebsiteSettingKeys;
use App\Support\Website\WebsiteMapEmbed;
use App\Support\Website\WebsiteUiLabelDefaults;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class WebsiteContentService
{
    public function __construct(
        protected WebsiteMediaService $media,
        protected WebsiteLandingBuilderService $landingBuilder,
    ) {}

    public function isCmsActive(): bool
    {
        if (! Schema::hasTable('website_settings')) {
            return false;
        }

        if (WebsiteSetting::query()->where('key', 'cms.active')->exists()) {
            return true;
        }

        return WebsiteSetting::query()
            ->whereIn('key', [
                'school_info',
                'landing_sections',
                'hero_stats',
                'theme',
                'seo',
            ])
            ->exists();
    }

    public function forLanding(bool $preview = false): array
    {
        $cacheKey = $preview ? 'website.landing.content.preview' : 'website.landing.content';

        $payload = $preview
            ? $this->buildFromDatabase($preview)
            : Cache::remember($cacheKey, 300, fn () => $this->buildFromDatabase(false));

        return LocalizedContent::resolve($payload, app()->getLocale());
    }

    public function clearCache(): void
    {
        $this->activateCms();
        Cache::forget('website.landing.content');
        Cache::forget('website.landing.content.preview');
    }

    public function activateCms(): void
    {
        if (! Schema::hasTable('website_settings')) {
            return;
        }

        if (WebsiteSetting::query()->where('key', 'cms.active')->exists()) {
            return;
        }

        WebsiteSetting::writeValue('cms.active', true);
    }

    public function buildFromDatabase(bool $preview = false): array
    {
        $defaults = WebsiteDefaultsRepository::load();

        $schoolInfo = WebsiteMapEmbed::normalizeSchoolInfo(
            WebsiteSetting::getValue('school_info', $defaults['schoolInfo'] ?? [])
        );
        $theme = WebsiteSetting::getValue('theme', $defaults['theme'] ?? []);
        $seo = WebsiteSetting::getValue('seo', $defaults['seo'] ?? []);
        $landingSections = WebsiteSetting::getValue('landing_sections', $defaults['landingSections'] ?? []);
        $admissionsFunnelHref = WebsiteSetting::getValue('admissions_funnel_href', $defaults['admissionsFunnelHref'] ?? '#visit');

        return [
            'schoolInfo' => $schoolInfo,
            'announcements' => $this->announcements($defaults),
            'navLinks' => $this->navLinks($defaults),
            'admissionsFunnelHref' => $admissionsFunnelHref,
            'heroStats' => WebsiteSetting::getValue('hero_stats', $defaults['heroStats'] ?? []),
            'parentTrustStrip' => WebsiteSetting::getValue('parent_trust_strip', $defaults['parentTrustStrip'] ?? []),
            'visitCampusReasons' => WebsiteSetting::getValue('visit_campus_reasons', $defaults['visitCampusReasons'] ?? []),
            'whatsappQuickActions' => WebsiteSetting::getValue('whatsapp_quick_actions', $defaults['whatsappQuickActions'] ?? []),
            'heroHighlights' => WebsiteSetting::getValue('hero_highlights', $defaults['heroHighlights'] ?? []),
            'heroBadges' => WebsiteSetting::getValue('hero_badges', $defaults['heroBadges'] ?? []),
            'ctaLibrary' => $this->ctaLibrary($defaults),
            'ctaPresets' => $this->ctaPresetsMap($defaults),
            'sectionCtas' => $this->sectionCtasResolved($defaults),
            'visitFormConfig' => $this->visitFormConfig($defaults),
            'uiLabels' => $this->uiLabels($defaults),
            'headerChrome' => WebsiteSetting::getValue(WebsiteSettingKeys::HEADER_CHROME, $defaults['headerChrome'] ?? []),
            'footerChrome' => WebsiteSetting::getValue(WebsiteSettingKeys::FOOTER_CHROME, $defaults['footerChrome'] ?? []),
            'campusVisit' => WebsiteSetting::getValue(WebsiteSettingKeys::CAMPUS_VISIT, $defaults['campusVisit'] ?? []),
            'admissionDocuments' => WebsiteSetting::getValue(WebsiteSettingKeys::ADMISSION_DOCUMENTS, $defaults['admissionDocuments'] ?? []),
            'floatingChrome' => WebsiteSetting::getValue(WebsiteSettingKeys::FLOATING_CHROME, $defaults['floatingChrome'] ?? []),
            'trustItems' => WebsiteSetting::getValue('trust_items', $defaults['trustItems'] ?? []),
            'coreValues' => WebsiteSetting::getValue('core_values', $defaults['coreValues'] ?? []),
            'whyItems' => WebsiteSetting::getValue('why_items', $defaults['whyItems'] ?? []),
            'studentLife' => WebsiteSetting::getValue('student_life', $defaults['studentLife'] ?? []),
            'facilities' => $this->facilities($defaults),
            'academicPrograms' => WebsiteSetting::getValue('academic_programs', $defaults['academicPrograms'] ?? []),
            'events' => $this->events($defaults),
            'newsItems' => $this->posts('news', $defaults),
            'blogPosts' => $this->posts('blog', $defaults),
            'galleryCategories' => WebsiteSetting::getValue('gallery_categories', $defaults['galleryCategories'] ?? []),
            'galleryItems' => $this->galleryItems($defaults),
            'achievements' => WebsiteSetting::getValue('achievements', $defaults['achievements'] ?? []),
            'testimonials' => $this->testimonials($defaults),
            'accreditations' => WebsiteSetting::getValue('accreditations', $defaults['accreditations'] ?? []),
            'admissionSteps' => WebsiteSetting::getValue('admission_steps', $defaults['admissionSteps'] ?? []),
            'stageShowcaseLabels' => WebsiteSetting::getValue('stage_showcase_labels', $defaults['stageShowcaseLabels'] ?? []),
            'stageModalUi' => WebsiteSetting::getValue('stage_modal_ui', $defaults['stageModalUi'] ?? []),
            'teacherRecruitment' => $this->teacherRecruitment($defaults),
            'studentSuccessStories' => $this->successStories($defaults),
            'faqs' => WebsiteSetting::getValue('faqs', $defaults['faqs'] ?? []),
            'stages' => $this->stages($defaults),
            'landingSections' => $landingSections,
            'pageBuilderSections' => $this->landingBuilder->sectionsForPublic($preview),
            'heroStatsEnabled' => $this->landingBuilder->isSectionEnabled('hero_stats'),
            'landingPage' => $this->landingPageMeta($preview),
            'theme' => $theme,
            'seo' => $seo,
            'careers' => $this->careers($defaults),
        ];
    }

    protected function landingPageMeta(bool $preview): ?array
    {
        $page = \App\Models\Website\WebsiteLandingPage::query()
            ->where('slug', config('website-landing-blocks.page_slug', 'school-talent'))
            ->first();

        if (! $page) {
            return null;
        }

        return [
            'status' => $page->status,
            'preview' => $preview,
        ];
    }

    public function importDefaults(): void
    {
        $data = WebsiteDefaultsRepository::load();
        if ($data === []) {
            return;
        }

        WebsiteSetting::putValue('cms.active', true);
        WebsiteSetting::putValue('school_info', $data['schoolInfo'] ?? []);
        WebsiteSetting::putValue('landing_sections', $data['landingSections'] ?? []);
        WebsiteSetting::putValue('theme', $data['theme'] ?? []);
        WebsiteSetting::putValue('seo', $data['seo'] ?? []);
        WebsiteSetting::putValue('admissions_funnel_href', $data['admissionsFunnelHref'] ?? '#visit');
        WebsiteSetting::putValue('hero_stats', $data['heroStats'] ?? []);
        WebsiteSetting::putValue('parent_trust_strip', $data['parentTrustStrip'] ?? []);
        WebsiteSetting::putValue('visit_campus_reasons', $data['visitCampusReasons'] ?? []);
        WebsiteSetting::putValue('whatsapp_quick_actions', $data['whatsappQuickActions'] ?? []);
        WebsiteSetting::putValue('hero_highlights', $data['heroHighlights'] ?? []);
        WebsiteSetting::putValue('hero_badges', $data['heroBadges'] ?? []);
        WebsiteSetting::putValue(WebsiteSettingKeys::CTA_LIBRARY, $this->ctaLibraryFromLegacy($data));
        WebsiteSetting::putValue('section_ctas', $this->sectionCtasToIds($data['sectionCtas'] ?? [], $data));
        WebsiteSetting::putValue(WebsiteSettingKeys::HEADER_CHROME, $data['headerChrome'] ?? WebsiteDefaultsRepository::builtinDefaults()['headerChrome']);
        WebsiteSetting::putValue(WebsiteSettingKeys::FOOTER_CHROME, $data['footerChrome'] ?? WebsiteDefaultsRepository::builtinDefaults()['footerChrome']);
        WebsiteSetting::putValue(WebsiteSettingKeys::CAMPUS_VISIT, $data['campusVisit'] ?? WebsiteDefaultsRepository::builtinDefaults()['campusVisit']);
        WebsiteSetting::putValue(WebsiteSettingKeys::ADMISSION_DOCUMENTS, $data['admissionDocuments'] ?? WebsiteDefaultsRepository::builtinDefaults()['admissionDocuments']);
        WebsiteSetting::putValue(WebsiteSettingKeys::FLOATING_CHROME, $data['floatingChrome'] ?? WebsiteDefaultsRepository::builtinDefaults()['floatingChrome']);
        WebsiteSetting::putValue('visit_form', $this->normalizeVisitFormConfig($data['visitFormConfig'] ?? []));
        WebsiteSetting::putValue(
            WebsiteSettingKeys::UI_LABELS,
            array_replace_recursive(WebsiteUiLabelDefaults::all(), $data['uiLabels'] ?? [])
        );
        WebsiteSetting::putValue('trust_items', $data['trustItems'] ?? []);
        WebsiteSetting::putValue('core_values', $data['coreValues'] ?? []);
        WebsiteSetting::putValue('why_items', $data['whyItems'] ?? []);
        WebsiteSetting::putValue('student_life', $data['studentLife'] ?? []);
        WebsiteSetting::putValue('academic_programs', $data['academicPrograms'] ?? []);
        WebsiteSetting::putValue('gallery_categories', $data['galleryCategories'] ?? []);
        WebsiteSetting::putValue('achievements', $data['achievements'] ?? []);
        WebsiteSetting::putValue('accreditations', $data['accreditations'] ?? []);
        WebsiteSetting::putValue('admission_steps', $data['admissionSteps'] ?? []);
        WebsiteSetting::putValue('stage_showcase_labels', $data['stageShowcaseLabels'] ?? []);
        WebsiteSetting::putValue('stage_modal_ui', $data['stageModalUi'] ?? WebsiteDefaultsRepository::builtinDefaults()['stageModalUi']);
        WebsiteSetting::putValue('faqs', $data['faqs'] ?? []);
        WebsiteSetting::putValue('teacher_recruitment', $data['teacherRecruitment'] ?? []);

        $this->importAnnouncements($data['announcements'] ?? []);
        $this->importNavLinks($data['navLinks'] ?? []);
        $this->importStages($data['stages'] ?? []);
        $this->importFacilities($data['facilities'] ?? []);
        $this->importEvents($data['events'] ?? []);
        $this->importPosts('news', $data['newsItems'] ?? []);
        $this->importPosts('blog', $data['blogPosts'] ?? []);
        $this->importTestimonials($data['testimonials'] ?? []);
        $this->importSuccessStories($data['studentSuccessStories'] ?? []);
        $this->importGallery($data['galleryItems'] ?? []);
        $this->importCareersFromRecruitment($data['teacherRecruitment'] ?? []);

        $this->clearCache();
    }

    protected function announcements(array $defaults): array
    {
        $rows = WebsiteAnnouncement::query()->where('is_active', true)->orderBy('sort_order')->get();
        if ($rows->isEmpty()) {
            return $defaults['announcements'] ?? [];
        }

        return $rows->map(fn ($r) => [
            'id' => $r->external_id ?? 'ann-'.$r->id,
            'text' => $r->text,
            'text_ar' => $r->text_ar,
            'href' => $r->href,
        ])->all();
    }

    protected function navLinks(array $defaults): array
    {
        $rows = WebsiteNavLink::query()->where('is_active', true)->orderBy('sort_order')->get();
        if ($rows->isEmpty()) {
            return $defaults['navLinks'] ?? [];
        }

        return $rows->map(fn ($r) => [
            'href' => $r->href,
            'label' => $r->label,
            'label_ar' => $r->label_ar,
        ])->all();
    }

    protected function facilities(array $defaults): array
    {
        $rows = WebsiteFacility::query()->with('imageMedia')->where('is_active', true)->orderBy('sort_order')->get();
        if ($rows->isEmpty()) {
            return $defaults['facilities'] ?? [];
        }

        return $rows->map(function ($r) {
            $image = $this->media->resolveImage($r->imageMedia, $r->image_src, $r->image_alt, $r->external_id);

            return [
                'id' => $r->external_id ?? 'fac-'.$r->id,
                'icon' => $r->icon,
                'name' => $r->name,
                'description' => $r->description,
                'benefit' => $r->benefit,
                'image' => $image,
            ];
        })->all();
    }

    protected function events(array $defaults): array
    {
        $rows = WebsiteEvent::query()->with('imageMedia')->where('is_active', true)->orderBy('sort_order')->get();
        if ($rows->isEmpty()) {
            return $defaults['events'] ?? [];
        }

        return $rows->map(function ($r) {
            return [
                'id' => $r->external_id ?? 'evt-'.$r->id,
                'slug' => $r->slug,
                'date' => $r->date,
                'dateShort' => $r->date_short,
                'title' => $r->title,
                'type' => $r->type,
                'isOpenDay' => $r->is_open_day,
                'limitedSeatsLabel' => $r->limited_seats_label,
                'audience' => $r->audience,
                'location' => $r->location,
                'image' => $this->media->resolveImage($r->imageMedia, $r->image_src, $r->image_alt),
                'cta' => $r->cta,
                'href' => $r->href,
            ];
        })->all();
    }

    protected function posts(string $type, array $defaults): array
    {
        $rows = WebsitePost::query()
            ->with('imageMedia')
            ->where('type', $type)
            ->where('is_active', true)
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->get();
        $fallback = $type === 'news' ? ($defaults['newsItems'] ?? []) : ($defaults['blogPosts'] ?? []);
        if ($rows->isEmpty()) {
            return array_map(fn ($item) => $this->hydratePostItem($item, $type), $fallback);
        }

        return $rows->map(fn ($r) => $this->mapPostRow($r, $type))->all();
    }

    protected function mapPostRow(WebsitePost $r, string $type): array
    {
        $slug = $r->slug ?: \Illuminate\Support\Str::slug($r->title);

        $image = $this->media->resolveImage($r->imageMedia, $r->image_src, $r->image_alt, $slug);
        if (! $image || empty($image['src'])) {
            $image = [
                'assetKey' => 'post-placeholder',
                'src' => 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=600&q=80',
                'alt' => $r->title,
            ];
        }

        return [
            'id' => $r->external_id ?? $type.'-'.$r->id,
            'slug' => $slug,
            'category' => $r->category,
            'title' => $r->title,
            'title_ar' => $r->title_ar,
            'publishedAt' => $r->published_at,
            'date' => $r->published_at,
            'image' => $image,
            'excerpt' => $r->summary,
            'excerpt_ar' => $r->summary_ar,
            'content' => $r->content,
            'content_ar' => $r->content_ar,
            'isFeatured' => (bool) $r->is_featured,
            'url' => route('school-talent.article', ['type' => $type, 'slug' => $slug]),
        ];
    }

    protected function hydratePostItem(array $item, string $type): array
    {
        $slug = $item['slug'] ?? \Illuminate\Support\Str::slug($item['title'] ?? 'post');

        return array_merge($item, [
            'slug' => $slug,
            'url' => $item['url'] ?? route('school-talent.article', ['type' => $type, 'slug' => $slug]),
            'isFeatured' => $item['isFeatured'] ?? false,
        ]);
    }

    protected function testimonials(array $defaults): array
    {
        $rows = WebsiteTestimonial::query()->with('photoMedia')->where('is_active', true)->orderBy('sort_order')->get();
        if ($rows->isEmpty()) {
            return $defaults['testimonials'] ?? [];
        }

        return $rows->map(fn ($r) => [
            'id' => $r->external_id ?? 'test-'.$r->id,
            'role' => $r->role,
            'roleType' => $r->role_type,
            'name' => $r->name,
            'quote' => $r->quote,
            'photo' => $this->media->resolveImage($r->photoMedia, $r->photo_src, $r->photo_alt),
        ])->all();
    }

    protected function successStories(array $defaults): array
    {
        $rows = WebsiteSuccessStory::query()->with('imageMedia')->where('is_active', true)->orderBy('sort_order')->get();
        if ($rows->isEmpty()) {
            return $defaults['studentSuccessStories'] ?? [];
        }

        return $rows->map(function ($r) {
            $image = $this->media->resolveImage($r->imageMedia, $r->image_src, $r->image_alt);

            return array_filter([
                'id' => $r->external_id ?? 'story-'.$r->id,
                'category' => $r->category,
                'title' => $r->achievement ?? $r->student_name,
                'text' => $r->story,
                'stat' => $r->stat_value,
                'statLabel' => $r->stat_label,
                'image' => $image,
            ], fn ($v) => $v !== null);
        })->all();
    }

    protected function galleryItems(array $defaults): array
    {
        $rows = WebsiteGalleryItem::query()->with('imageMedia')->where('is_active', true)->orderBy('sort_order')->get();
        if ($rows->isEmpty()) {
            return $defaults['galleryItems'] ?? [];
        }

        return $rows->map(function ($r) {
            $image = $this->media->resolveImage($r->imageMedia, $r->src, $r->alt, $r->external_id);
            $src = $image['src'] ?? 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=500&q=80';

            return [
                'id' => $r->external_id ?? 'gal-'.$r->id,
                'category' => $r->category,
                'src' => $src,
                'alt' => $r->alt,
                'assetKey' => $r->external_id,
                'isFeatured' => (bool) ($r->is_featured ?? false),
            ];
        })->all();
    }

    protected function ctaLibrary(array $defaults): array
    {
        $library = WebsiteSetting::getValue(WebsiteSettingKeys::CTA_LIBRARY, []);
        if ($library !== []) {
            return $library;
        }

        return $this->ctaLibraryFromLegacy($defaults);
    }

    protected function ctaLibraryFromLegacy(array $data): array
    {
        $stored = WebsiteSetting::getValue(WebsiteSettingKeys::CTA_LIBRARY, []);
        if ($stored !== []) {
            return $stored;
        }

        $presets = $data['ctaLibrary'] ?? $data['ctaPresets'] ?? [];
        if (isset($presets[0]['id'])) {
            return $presets;
        }

        $href = $data['admissionsFunnelHref'] ?? '#visit';
        $map = [];
        foreach ($presets as $id => $preset) {
            $map[] = [
                'id' => $id,
                'label' => $preset['label'] ?? $id,
                'href' => $preset['href'] ?? $href,
                'variant' => $preset['variant'] ?? 'outline',
            ];
        }

        return $map ?: WebsiteDefaultsRepository::builtinDefaults()['ctaLibrary'];
    }

    protected function ctaPresetsMap(array $defaults): array
    {
        $out = [];
        foreach ($this->ctaLibrary($defaults) as $cta) {
            $out[$cta['id']] = $cta;
        }

        return $out;
    }

    protected function sectionCtasResolved(array $defaults): array
    {
        $raw = WebsiteSetting::getValue('section_ctas', $defaults['sectionCtas'] ?? []);
        $library = collect($this->ctaLibrary($defaults))->keyBy('id');
        $resolved = [];

        foreach ($raw as $section => $ctas) {
            $resolved[$section] = collect($ctas)->map(function ($cta) use ($library) {
                if (is_string($cta)) {
                    return $library->get($cta, ['id' => $cta, 'label' => $cta, 'href' => '#', 'variant' => 'outline']);
                }
                if (isset($cta['id']) && $library->has($cta['id'])) {
                    return $library->get($cta['id']);
                }

                return $cta;
            })->filter()->values()->all();
        }

        return $resolved;
    }

    protected function sectionCtasToIds(array $sectionCtas, array $data): array
    {
        if ($sectionCtas === []) {
            return [];
        }

        $library = collect($this->ctaLibraryFromLegacy($data));
        $out = [];

        foreach ($sectionCtas as $section => $ctas) {
            $out[$section] = collect($ctas)->map(function ($cta) use ($library) {
                if (is_string($cta)) {
                    return $cta;
                }
                if (is_array($cta) && isset($cta['id'])) {
                    return $cta['id'];
                }
                if (is_array($cta)) {
                    $match = $library->firstWhere('label', $cta['label'] ?? '');

                    return $match['id'] ?? 'visit';
                }

                return $cta;
            })->values()->all();
        }

        return $out;
    }

    protected function stages(array $defaults): array
    {
        $rows = WebsiteStage::query()->with('imageMedia')->where('is_active', true)->orderBy('sort_order')->get();
        if ($rows->isEmpty()) {
            return $defaults['stages'] ?? [];
        }

        return $rows->map(function ($r) {
            $payload = $r->payload ?? [];
            $image = $this->media->resolveImage($r->imageMedia, $r->image_src, $r->image_alt, $r->slug);

            return array_merge($payload, [
                'id' => $payload['id'] ?? $r->slug,
                'slug' => $r->slug,
                'title' => $r->title,
                'subtitle' => $r->subtitle,
                'ageRange' => $r->age_range,
                'tagline' => $r->tagline,
                'tone' => $r->tone,
                'studentCount' => $r->student_count,
                'classSize' => $r->class_size,
                'keySkills' => $r->key_skills ?? [],
                'image' => $image ?? ($payload['image'] ?? null),
            ]);
        })->all();
    }

    protected function teacherRecruitment(array $defaults): array
    {
        $block = WebsiteSetting::getValue('teacher_recruitment', $defaults['teacherRecruitment'] ?? []);
        $positions = WebsiteCareer::query()->where('is_active', true)->orderBy('sort_order')->pluck('title')->all();
        if ($positions !== []) {
            $block['vacancies'] = $positions;
        }

        return $block;
    }

    protected function careers(array $defaults): array
    {
        $rows = WebsiteCareer::query()->where('is_active', true)->orderBy('sort_order')->get();
        if ($rows->isEmpty()) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'id' => $r->id,
            'title' => $r->title,
            'department' => $r->department,
            'type' => $r->type,
            'description' => $r->description,
            'apply_url' => $r->apply_url,
        ])->all();
    }

    protected function importAnnouncements(array $items): void
    {
        WebsiteAnnouncement::query()->delete();
        foreach ($items as $i => $item) {
            WebsiteAnnouncement::query()->create([
                'external_id' => $item['id'] ?? null,
                'text' => $item['text'],
                'href' => $item['href'] ?? null,
                'sort_order' => $i,
            ]);
        }
    }

    protected function importNavLinks(array $items): void
    {
        WebsiteNavLink::query()->delete();
        foreach ($items as $i => $item) {
            WebsiteNavLink::query()->create([
                'label' => $item['label'],
                'href' => $item['href'],
                'sort_order' => $i,
            ]);
        }
    }

    protected function importStages(array $items): void
    {
        WebsiteStage::query()->delete();
        foreach ($items as $i => $stage) {
            $image = $stage['image'] ?? null;
            WebsiteStage::query()->create([
                'slug' => $stage['slug'] ?? $stage['id'],
                'title' => $stage['title'],
                'subtitle' => $stage['subtitle'] ?? null,
                'age_range' => $stage['ageRange'] ?? null,
                'tagline' => $stage['tagline'] ?? null,
                'tone' => $stage['tone'] ?? null,
                'student_count' => $stage['studentCount'] ?? null,
                'class_size' => $stage['classSize'] ?? null,
                'key_skills' => $stage['keySkills'] ?? [],
                'image_src' => is_array($image) ? ($image['src'] ?? null) : null,
                'image_alt' => is_array($image) ? ($image['alt'] ?? null) : null,
                'payload' => $stage,
                'sort_order' => $i,
            ]);
        }
    }

    protected function importFacilities(array $items): void
    {
        WebsiteFacility::query()->delete();
        foreach ($items as $i => $item) {
            $image = $item['image'] ?? null;
            WebsiteFacility::query()->create([
                'external_id' => $item['id'] ?? null,
                'icon' => $item['icon'] ?? null,
                'name' => $item['name'],
                'description' => $item['description'] ?? null,
                'benefit' => $item['benefit'] ?? null,
                'image_src' => is_array($image) ? ($image['src'] ?? null) : null,
                'image_alt' => is_array($image) ? ($image['alt'] ?? null) : null,
                'sort_order' => $i,
            ]);
        }
    }

    protected function importEvents(array $items): void
    {
        WebsiteEvent::query()->delete();
        foreach ($items as $i => $item) {
            $image = $item['image'] ?? null;
            WebsiteEvent::query()->create([
                'external_id' => $item['id'] ?? null,
                'slug' => $item['slug'] ?? null,
                'title' => $item['title'],
                'type' => $item['type'] ?? null,
                'date' => $item['date'] ?? null,
                'date_short' => $item['dateShort'] ?? null,
                'audience' => $item['audience'] ?? null,
                'location' => $item['location'] ?? null,
                'is_open_day' => $item['isOpenDay'] ?? false,
                'limited_seats_label' => $item['limitedSeatsLabel'] ?? null,
                'cta' => $item['cta'] ?? null,
                'href' => $item['href'] ?? null,
                'image_src' => is_array($image) ? ($image['src'] ?? null) : null,
                'image_alt' => is_array($image) ? ($image['alt'] ?? null) : null,
                'sort_order' => $i,
            ]);
        }
    }

    protected function importPosts(string $type, array $items): void
    {
        WebsitePost::query()->where('type', $type)->delete();
        foreach ($items as $i => $item) {
            $image = $item['image'] ?? null;
            WebsitePost::query()->create([
                'type' => $type,
                'external_id' => $item['id'] ?? null,
                'slug' => $item['slug'] ?? null,
                'title' => $item['title'],
                'category' => $item['category'] ?? null,
                'published_at' => $item['publishedAt'] ?? $item['date'] ?? null,
                'summary' => $item['excerpt'] ?? null,
                'is_featured' => $i === 0,
                'image_src' => is_array($image) ? ($image['src'] ?? null) : null,
                'image_alt' => is_array($image) ? ($image['alt'] ?? null) : null,
                'sort_order' => $i,
            ]);
        }
    }

    protected function importTestimonials(array $items): void
    {
        WebsiteTestimonial::query()->delete();
        foreach ($items as $i => $item) {
            $photo = $item['photo'] ?? null;
            WebsiteTestimonial::query()->create([
                'external_id' => $item['id'] ?? null,
                'name' => $item['name'],
                'role' => $item['role'],
                'role_type' => $item['roleType'] ?? null,
                'quote' => $item['quote'],
                'photo_src' => is_array($photo) ? ($photo['src'] ?? null) : null,
                'photo_alt' => is_array($photo) ? ($photo['alt'] ?? null) : null,
                'sort_order' => $i,
            ]);
        }
    }

    protected function importSuccessStories(array $items): void
    {
        WebsiteSuccessStory::query()->delete();
        foreach ($items as $i => $item) {
            $image = $item['image'] ?? null;
            WebsiteSuccessStory::query()->create([
                'external_id' => $item['id'] ?? null,
                'student_name' => $item['title'] ?? $item['name'] ?? '',
                'achievement' => $item['title'] ?? null,
                'category' => $item['category'] ?? null,
                'story' => $item['text'] ?? $item['story'] ?? null,
                'stat_value' => $item['stat'] ?? null,
                'stat_label' => $item['statLabel'] ?? null,
                'image_src' => is_array($image) ? ($image['src'] ?? null) : null,
                'image_alt' => is_array($image) ? ($image['alt'] ?? null) : null,
                'sort_order' => $i,
            ]);
        }
    }

    protected function importGallery(array $items): void
    {
        WebsiteGalleryItem::query()->delete();
        foreach ($items as $i => $item) {
            WebsiteGalleryItem::query()->create([
                'external_id' => $item['id'] ?? $item['assetKey'] ?? null,
                'category' => $item['category'],
                'src' => $item['src'],
                'alt' => $item['alt'] ?? null,
                'sort_order' => $i,
            ]);
        }
    }

    protected function importCareersFromRecruitment(array $block): void
    {
        WebsiteCareer::query()->delete();
        foreach ($block['vacancies'] ?? [] as $i => $title) {
            WebsiteCareer::query()->create([
                'title' => $title,
                'type' => 'teacher',
                'sort_order' => $i,
            ]);
        }
    }

    protected function uiLabels(array $defaults): array
    {
        return array_replace_recursive(
            WebsiteUiLabelDefaults::all(),
            WebsiteSetting::getValue(WebsiteSettingKeys::UI_LABELS, $defaults['uiLabels'] ?? [])
        );
    }

    protected function visitFormConfig(array $defaults): array
    {
        return $this->normalizeVisitFormConfig(
            WebsiteSetting::getValue('visit_form', $defaults['visitFormConfig'] ?? [])
        );
    }

    public function normalizeVisitFormConfig(array $config): array
    {
        $defaults = WebsiteDefaultsRepository::builtinDefaults()['visitFormConfig'] ?? [];
        $config = array_replace_recursive($defaults, $config);

        if (empty($config['fields'])) {
            $config['fields'] = WebsiteUiLabelDefaults::defaultVisitFormFields();
        }

        usort($config['fields'], fn ($a, $b) => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));

        return $config;
    }
}
