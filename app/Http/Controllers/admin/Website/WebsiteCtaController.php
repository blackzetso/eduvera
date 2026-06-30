<?php

namespace App\Http\Controllers\admin\Website;

use App\Http\Controllers\Controller;
use App\Models\Website\WebsiteSetting;
use App\Services\Website\WebsiteContentService;
use App\Support\Website\WebsiteDefaultsRepository;
use App\Support\Website\WebsiteSettingKeys;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebsiteCtaController extends Controller
{
    public function __construct(protected WebsiteContentService $cms) {}

    public function index()
    {
        $defaults = WebsiteDefaultsRepository::load();

        return Inertia::render('Admin/theme1/Website/Ctas/Index', [
            'ctaLibrary' => WebsiteSetting::getValue(WebsiteSettingKeys::CTA_LIBRARY, $defaults['ctaLibrary'] ?? []),
            'sectionCtas' => WebsiteSetting::getValue('section_ctas', $defaults['sectionCtas'] ?? []),
            'sectionKeys' => array_keys($defaults['sectionCtas'] ?? []),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'cta_library' => 'required|array',
            'cta_library.*.id' => 'required|string|max:64',
            'cta_library.*.label' => 'required|string|max:120',
            'cta_library.*.href' => 'required|string|max:255',
            'cta_library.*.variant' => 'nullable|string|max:32',
            'section_ctas' => 'nullable|array',
        ]);

        WebsiteSetting::putValue(WebsiteSettingKeys::CTA_LIBRARY, $data['cta_library']);
        WebsiteSetting::putValue('section_ctas', $data['section_ctas'] ?? []);
        $this->cms->clearCache();

        return back()->with('success', 'CTA library saved.');
    }
}
