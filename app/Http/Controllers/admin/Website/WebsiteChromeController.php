<?php

namespace App\Http\Controllers\admin\Website;

use App\Http\Controllers\Controller;
use App\Models\Website\WebsiteSetting;
use App\Services\Website\WebsiteContentService;
use App\Services\Website\WebsiteMediaService;
use App\Support\Website\WebsiteDefaultsRepository;
use App\Support\Website\WebsiteSettingKeys;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebsiteChromeController extends Controller
{
    public function __construct(
        protected WebsiteContentService $cms,
        protected WebsiteMediaService $media,
    ) {}

    public function edit()
    {
        $defaults = WebsiteDefaultsRepository::load();

        return Inertia::render('Admin/theme1/Website/Chrome/Edit', [
            'headerChrome' => WebsiteSetting::getValue(WebsiteSettingKeys::HEADER_CHROME, $defaults['headerChrome'] ?? []),
            'footerChrome' => WebsiteSetting::getValue(WebsiteSettingKeys::FOOTER_CHROME, $defaults['footerChrome'] ?? []),
            'schoolInfo' => WebsiteSetting::getValue('school_info', $defaults['schoolInfo'] ?? []),
            'theme' => WebsiteSetting::getValue('theme', $defaults['theme'] ?? []),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'header_chrome' => 'required|array',
            'footer_chrome' => 'required|array',
            'school_info' => 'required|array',
            'theme' => 'nullable|array',
            'logo' => 'nullable|image|max:2048',
            'favicon' => 'nullable|image|max:1024',
        ]);

        WebsiteSetting::putValue(WebsiteSettingKeys::HEADER_CHROME, $data['header_chrome']);
        WebsiteSetting::putValue(WebsiteSettingKeys::FOOTER_CHROME, $data['footer_chrome']);

        $schoolInfo = $data['school_info'];
        $existing = WebsiteSetting::getValue('school_info', []);
        WebsiteSetting::putValue('school_info', array_replace_recursive($existing, $schoolInfo));

        $theme = $data['theme'] ?? WebsiteSetting::getValue('theme', []);
        if ($request->hasFile('logo')) {
            $media = $this->media->store($request->file('logo'), 'Site logo');
            $theme['logo_path'] = $media->url();
        }
        if ($request->hasFile('favicon')) {
            $media = $this->media->store($request->file('favicon'), 'Favicon');
            $theme['favicon_path'] = $media->url();
        }
        WebsiteSetting::putValue('theme', $theme);

        $this->cms->clearCache();

        return back()->with('success', 'Header & footer saved.');
    }
}
