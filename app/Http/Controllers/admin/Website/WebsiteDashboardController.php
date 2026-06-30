<?php

namespace App\Http\Controllers\admin\Website;

use App\Http\Controllers\Controller;
use App\Models\Website\WebsiteLandingPage;
use App\Services\Website\WebsiteContentService;
use App\Services\Website\WebsiteLandingBuilderService;
use Inertia\Inertia;

class WebsiteDashboardController extends Controller
{
    public function index(WebsiteContentService $cms)
    {
        return Inertia::render('Admin/theme1/Website/Index', [
            'cmsActive' => $cms->isCmsActive(),
        ]);
    }

    public function importDefaults(WebsiteContentService $cms, WebsiteLandingBuilderService $builder)
    {
        $cms->importDefaults();

        $slug = config('website-landing-blocks.page_slug', 'school-talent');
        if (! WebsiteLandingPage::query()->where('slug', $slug)->exists()) {
            $builder->seedPageFromLegacySettings();
        }

        return redirect()->route('admin.website.index')->with('success', 'تم استيراد محتوى الصفحة الرئيسية بنجاح.');
    }
}
