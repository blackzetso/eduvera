<?php

namespace App\Http\Controllers\admin\Website;

use App\Http\Controllers\Controller;
use App\Models\Website\WebsiteSetting;
use App\Services\Website\WebsiteContentService;
use App\Support\Website\WebsiteDefaultsRepository;
use App\Support\Website\WebsiteSettingKeys;
use App\Support\Website\WebsiteUiLabelDefaults;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebsiteUiLabelsController extends Controller
{
    public function __construct(protected WebsiteContentService $cms) {}

    public function edit()
    {
        $defaults = WebsiteDefaultsRepository::load();
        $uiLabels = array_replace_recursive(
            WebsiteUiLabelDefaults::all(),
            WebsiteSetting::getValue(WebsiteSettingKeys::UI_LABELS, $defaults['uiLabels'] ?? [])
        );

        return Inertia::render('Admin/theme1/Website/UiLabels/Edit', [
            'uiLabels' => $uiLabels,
            'fields' => WebsiteUiLabelDefaults::adminFields(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'uiLabels' => 'required|array',
            'uiLabels.global' => 'nullable|array',
            'uiLabels.cta' => 'nullable|array',
            'uiLabels.hero' => 'nullable|array',
            'uiLabels.hero.trust_avatars' => 'nullable|array',
        ]);

        $uiLabels = array_replace_recursive(WebsiteUiLabelDefaults::all(), $data['uiLabels']);
        WebsiteSetting::putValue(WebsiteSettingKeys::UI_LABELS, $uiLabels);
        $this->syncCtaLibraryLabels($uiLabels['cta'] ?? []);

        $this->cms->clearCache();

        return back()->with('success', 'تم حفظ تسميات الواجهة.');
    }

    protected function syncCtaLibraryLabels(array $ctaLabels): void
    {
        if ($ctaLabels === []) {
            return;
        }

        $library = WebsiteSetting::getValue(WebsiteSettingKeys::CTA_LIBRARY, []);
        if (! is_array($library) || $library === []) {
            $library = WebsiteDefaultsRepository::builtinDefaults()['ctaLibrary'] ?? [];
        }

        foreach ($library as $i => $cta) {
            $id = $cta['id'] ?? null;
            if ($id && isset($ctaLabels[$id]) && $ctaLabels[$id] !== '') {
                $library[$i]['label'] = $ctaLabels[$id];
            }
        }

        WebsiteSetting::putValue(WebsiteSettingKeys::CTA_LIBRARY, $library);
    }
}
