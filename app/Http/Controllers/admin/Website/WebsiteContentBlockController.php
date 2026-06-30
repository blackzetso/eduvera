<?php

namespace App\Http\Controllers\admin\Website;

use App\Http\Controllers\Controller;
use App\Models\Website\WebsiteSetting;
use App\Services\Website\WebsiteContentService;
use App\Support\Website\WebsiteDefaultsRepository;
use App\Support\Website\WebsiteSettingKeys;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebsiteContentBlockController extends Controller
{
    public function __construct(protected WebsiteContentService $cms) {}

    public function index()
    {
        $blocks = collect(WebsiteSettingKeys::contentBlockMap())
            ->map(fn ($key, $slug) => [
                'slug' => $slug,
                'title' => WebsiteSettingKeys::contentBlockTitle($slug),
                'settingKey' => $key,
            ])
            ->values();

        return Inertia::render('Admin/theme1/Website/ContentBlocks/Index', ['blocks' => $blocks]);
    }

    public function edit(string $block)
    {
        $map = WebsiteSettingKeys::contentBlockMap();
        if (! isset($map[$block])) {
            abort(404);
        }

        $key = $map[$block];
        $defaults = WebsiteDefaultsRepository::load();

        return Inertia::render('Admin/theme1/Website/ContentBlocks/Edit', [
            'block' => $block,
            'title' => WebsiteSettingKeys::contentBlockTitle($block),
            'settingKey' => $key,
            'items' => WebsiteSetting::getValue($key, $defaults[$this->defaultsKey($key)] ?? []),
            'schema' => $this->schema($block),
        ]);
    }

    public function update(Request $request, string $block)
    {
        $map = WebsiteSettingKeys::contentBlockMap();
        if (! isset($map[$block])) {
            abort(404);
        }

        $key = $map[$block];
        $data = $request->validate([
            'items' => 'required|array',
        ]);

        WebsiteSetting::putValue($key, $data['items']);
        $this->cms->clearCache();

        return back()->with('success', 'Saved.');
    }

    protected function defaultsKey(string $settingKey): string
    {
        return match ($settingKey) {
            'trust_items' => 'trustItems',
            'core_values' => 'coreValues',
            'why_items' => 'whyItems',
            'parent_trust_strip' => 'parentTrustStrip',
            'academic_programs' => 'academicPrograms',
            'gallery_categories' => 'galleryCategories',
            'hero_badges' => 'heroBadges',
            'hero_highlights' => 'heroHighlights',
            'stage_showcase_labels' => 'stageShowcaseLabels',
            'stage_modal_ui' => 'stageModalUi',
            'student_life' => 'studentLife',
            default => $settingKey,
        };
    }

    protected function schema(string $block): string
    {
        return match ($block) {
            'trust-strip' => 'string_list',
            'gallery-categories' => 'string_list',
            'stage-showcase-labels' => 'key_value',
            'stage-modal-ui' => 'stage_modal',
            'student-life' => 'student_life',
            'core-values', 'why-choose', 'parent-trust', 'academic-programs', 'achievements', 'hero-badges', 'hero-highlights' => 'object_list',
            'accreditations' => 'accreditation_list',
            'faqs' => 'faq_list',
            default => 'object_list',
        };
    }
}
