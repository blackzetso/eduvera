<?php

namespace App\Http\Controllers\admin\Website;

use App\Http\Controllers\Controller;
use App\Models\Website\WebsiteSetting;
use App\Services\Admission\AdmissionDocumentDefinitionService;
use App\Services\Website\WebsiteContentService;
use App\Services\Website\WebsiteMediaService;
use App\Support\Website\WebsiteDefaultsRepository;
use App\Support\Website\WebsiteMapEmbed;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebsiteSettingsController extends Controller
{
    public function __construct(
        protected WebsiteContentService $cms,
        protected WebsiteMediaService $media,
        protected AdmissionDocumentDefinitionService $documentDefinitions,
    ) {}

    public function landing()
    {
        return Inertia::render('Admin/theme1/Website/LandingSettings', [
            'landingSections' => WebsiteSetting::getValue('landing_sections', WebsiteDefaultsRepository::load()['landingSections'] ?? []),
        ]);
    }

    public function updateLanding(Request $request)
    {
        $data = $request->validate([
            'landingSections' => 'required|array',
            'landingSections.*.key' => 'required|string',
            'landingSections.*.label' => 'required|string',
            'landingSections.*.enabled' => 'boolean',
            'landingSections.*.sort_order' => 'integer',
        ]);

        WebsiteSetting::putValue('landing_sections', $data['landingSections']);
        $this->cms->clearCache();

        return back()->with('success', 'تم حفظ إعدادات الأقسام.');
    }

    public function hero()
    {
        $defaults = WebsiteDefaultsRepository::load();

        return Inertia::render('Admin/theme1/Website/Hero', [
            'schoolInfo' => WebsiteSetting::getValue('school_info', $defaults['schoolInfo'] ?? []),
            'heroStats' => WebsiteSetting::getValue('hero_stats', $defaults['heroStats'] ?? []),
            'heroHighlights' => WebsiteSetting::getValue('hero_highlights', $defaults['heroHighlights'] ?? []),
            'heroBadges' => WebsiteSetting::getValue('hero_badges', $defaults['heroBadges'] ?? []),
            'ctaPresets' => WebsiteSetting::getValue('cta_presets', $defaults['ctaPresets'] ?? []),
        ]);
    }

    public function updateHero(Request $request)
    {
        $data = $request->validate([
            'schoolInfo' => 'required|array',
            'heroStats' => 'nullable|array',
            'heroHighlights' => 'nullable|array',
            'heroBadges' => 'nullable|array',
            'ctaPresets' => 'nullable|array',
            'hero_image' => 'nullable|image|max:5120',
            'hero_background' => 'nullable|image|max:5120',
        ]);

        $schoolInfo = WebsiteMapEmbed::normalizeSchoolInfo($data['schoolInfo']);
        if ($request->hasFile('hero_image')) {
            $media = $this->media->store($request->file('hero_image'), 'Hero image');
            $schoolInfo['hero']['image'] = $media->toImageRef('Hero');
        }
        if ($request->hasFile('hero_background')) {
            $media = $this->media->store($request->file('hero_background'), 'Hero background');
            $schoolInfo['hero']['backgroundImage'] = $media->toImageRef('Hero background');
        }

        WebsiteSetting::putValue('school_info', $schoolInfo);
        WebsiteSetting::putValue('hero_stats', $data['heroStats'] ?? []);
        WebsiteSetting::putValue('hero_highlights', $data['heroHighlights'] ?? []);
        WebsiteSetting::putValue('hero_badges', $data['heroBadges'] ?? []);
        WebsiteSetting::putValue('cta_presets', $data['ctaPresets'] ?? []);
        $this->cms->clearCache();

        return back()->with('success', 'تم حفظ قسم البطل.');
    }

    public function schoolInfo()
    {
        $defaults = WebsiteDefaultsRepository::load();

        return Inertia::render('Admin/theme1/Website/SchoolInfo', [
            'schoolInfo' => WebsiteSetting::getValue('school_info', $defaults['schoolInfo'] ?? []),
        ]);
    }

    public function updateSchoolInfo(Request $request)
    {
        $data = $request->validate([
            'schoolInfo' => 'required|array',
            'logo' => 'nullable|image|max:2048',
            'about_image' => 'nullable|image|max:5120',
            'principal_image' => 'nullable|image|max:5120',
        ]);

        $schoolInfo = WebsiteMapEmbed::normalizeSchoolInfo($data['schoolInfo']);
        if ($request->hasFile('logo')) {
            $media = $this->media->store($request->file('logo'), 'School logo');
            $schoolInfo['logo'] = $media->toImageRef('Logo');
        }
        if ($request->hasFile('about_image')) {
            $media = $this->media->store($request->file('about_image'), 'About');
            $schoolInfo['about']['image'] = $media->toImageRef('About');
        }
        if ($request->hasFile('principal_image')) {
            $media = $this->media->store($request->file('principal_image'), 'Principal');
            $schoolInfo['principal']['image'] = $media->toImageRef('Principal');
        }

        WebsiteSetting::putValue('school_info', $schoolInfo);
        $this->cms->clearCache();

        return back()->with('success', 'تم حفظ معلومات المدرسة.');
    }

    public function admissions()
    {
        $defaults = WebsiteDefaultsRepository::load();

        return Inertia::render('Admin/theme1/Website/Admissions', [
            'admissionSteps' => WebsiteSetting::getValue('admission_steps', $defaults['admissionSteps'] ?? []),
            'admissionsFunnelHref' => WebsiteSetting::getValue('admissions_funnel_href', $defaults['admissionsFunnelHref'] ?? '#visit'),
            'visitFormConfig' => $this->cms->normalizeVisitFormConfig(
                WebsiteSetting::getValue('visit_form', $defaults['visitFormConfig'] ?? [])
            ),
            'visitCampusReasons' => WebsiteSetting::getValue('visit_campus_reasons', $defaults['visitCampusReasons'] ?? []),
            'campusVisit' => WebsiteSetting::getValue(\App\Support\Website\WebsiteSettingKeys::CAMPUS_VISIT, $defaults['campusVisit'] ?? []),
            'admissionDocuments' => $this->documentDefinitions->forPublicDisplay(),
            'admissionDocumentsSettingsUrl' => route('admin.admissions.settings.documents'),
        ]);
    }

    public function updateAdmissions(Request $request)
    {
        $data = $request->validate([
            'admissionSteps' => 'nullable|array',
            'admissionsFunnelHref' => 'nullable|string|max:255',
            'visitFormConfig' => 'nullable|array',
            'visitCampusReasons' => 'nullable|array',
            'campusVisit' => 'nullable|array',
        ]);

        WebsiteSetting::putValue('admission_steps', $data['admissionSteps'] ?? []);
        WebsiteSetting::putValue('admissions_funnel_href', $data['admissionsFunnelHref'] ?? '#visit');
        WebsiteSetting::putValue('visit_form', $this->cms->normalizeVisitFormConfig($data['visitFormConfig'] ?? []));
        WebsiteSetting::putValue('visit_campus_reasons', $data['visitCampusReasons'] ?? []);
        WebsiteSetting::putValue(\App\Support\Website\WebsiteSettingKeys::CAMPUS_VISIT, $data['campusVisit'] ?? []);
        $this->cms->clearCache();

        return back()->with('success', 'تم حفظ إعدادات القبول.');
    }

    public function contact()
    {
        $defaults = WebsiteDefaultsRepository::load();

        return Inertia::render('Admin/theme1/Website/Contact', [
            'schoolInfo' => WebsiteSetting::getValue('school_info', $defaults['schoolInfo'] ?? []),
            'visitCampusReasons' => WebsiteSetting::getValue('visit_campus_reasons', $defaults['visitCampusReasons'] ?? []),
        ]);
    }

    public function updateContact(Request $request)
    {
        $data = $request->validate([
            'schoolInfo' => 'required|array',
            'visitCampusReasons' => 'nullable|array',
        ]);

        $existing = WebsiteSetting::getValue('school_info', []);
        $schoolInfo = WebsiteMapEmbed::normalizeSchoolInfo($data['schoolInfo']);
        WebsiteSetting::putValue('school_info', array_replace_recursive($existing, $schoolInfo));
        WebsiteSetting::putValue('visit_campus_reasons', $data['visitCampusReasons'] ?? []);
        $this->cms->clearCache();

        return back()->with('success', 'تم حفظ معلومات التواصل.');
    }

    public function social()
    {
        $defaults = WebsiteDefaultsRepository::load();
        $schoolInfo = WebsiteSetting::getValue('school_info', $defaults['schoolInfo'] ?? []);

        return Inertia::render('Admin/theme1/Website/Social', [
            'social' => $schoolInfo['social'] ?? [],
            'whatsappQuickActions' => WebsiteSetting::getValue('whatsapp_quick_actions', $defaults['whatsappQuickActions'] ?? []),
        ]);
    }

    public function updateSocial(Request $request)
    {
        $data = $request->validate([
            'social' => 'required|array',
            'whatsappQuickActions' => 'nullable|array',
        ]);

        $schoolInfo = WebsiteSetting::getValue('school_info', []);
        $schoolInfo['social'] = $data['social'];
        WebsiteSetting::putValue('school_info', $schoolInfo);
        WebsiteSetting::putValue('whatsapp_quick_actions', $data['whatsappQuickActions'] ?? []);
        $this->cms->clearCache();

        return back()->with('success', 'تم حفظ وسائل التواصل.');
    }

    public function seo()
    {
        return Inertia::render('Admin/theme1/Website/Seo', [
            'seo' => WebsiteSetting::getValue('seo', WebsiteDefaultsRepository::load()['seo'] ?? []),
        ]);
    }

    public function updateSeo(Request $request)
    {
        $data = $request->validate([
            'seo' => 'required|array',
            'og_image' => 'nullable|image|max:5120',
        ]);

        $seo = $data['seo'];
        if ($request->hasFile('og_image')) {
            $media = $this->media->store($request->file('og_image'), 'Open Graph');
            $seo['og_image_path'] = $media->url();
        }

        WebsiteSetting::putValue('seo', $seo);
        $this->cms->clearCache();

        return back()->with('success', 'تم حفظ إعدادات SEO.');
    }

    public function theme()
    {
        return Inertia::render('Admin/theme1/Website/Theme', [
            'theme' => WebsiteSetting::getValue('theme', WebsiteDefaultsRepository::load()['theme'] ?? []),
        ]);
    }

    public function updateTheme(Request $request)
    {
        $data = $request->validate([
            'theme' => 'required|array',
            'logo' => 'nullable|image|max:2048',
            'favicon' => 'nullable|image|max:1024',
        ]);

        $theme = $data['theme'];
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

        return back()->with('success', 'تم حفظ الهوية البصرية.');
    }
}
