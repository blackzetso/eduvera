<?php

namespace App\Http\Controllers\admin\Website;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ValidatesBilingualFields;
use App\Models\Website\WebsiteAnnouncement;
use App\Services\Website\WebsiteContentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebsiteAnnouncementController extends Controller
{
    use ValidatesBilingualFields;

    public function __construct(protected WebsiteContentService $cms) {}

    public function index()
    {
        return Inertia::render('Admin/theme1/Website/Announcements/Index', [
            'announcements' => WebsiteAnnouncement::query()->orderBy('sort_order')->get(),
            'announcementBadge' => \App\Models\Website\WebsiteSetting::getValue('header_chrome', [])['announcement_badge'] ?? 'New',
            'announcementBadgeAr' => \App\Models\Website\WebsiteSetting::getValue('header_chrome', [])['announcement_badge_ar'] ?? '',
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(array_merge([
            'href' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ], $this->bilingualFieldRules('text', 255)));
        WebsiteAnnouncement::query()->create($data);
        $this->cms->clearCache();

        return back()->with('success', 'Announcement added.');
    }

    public function update(Request $request, WebsiteAnnouncement $announcement)
    {
        $data = $request->validate(array_merge([
            'href' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ], $this->bilingualFieldRules('text', 255)));
        $announcement->update($data);
        $this->cms->clearCache();

        return back()->with('success', 'Announcement updated.');
    }

    public function destroy(WebsiteAnnouncement $announcement)
    {
        $announcement->delete();
        $this->cms->clearCache();

        return back()->with('success', 'Announcement removed.');
    }

    public function updateBadge(Request $request)
    {
        $data = $request->validate(array_merge([
            'announcement_badge' => 'nullable|string|max:32|required_without:announcement_badge_ar',
            'announcement_badge_ar' => 'nullable|string|max:32|required_without:announcement_badge',
        ]));
        $chrome = \App\Models\Website\WebsiteSetting::getValue('header_chrome', []);
        $chrome['announcement_badge'] = $data['announcement_badge'];
        $chrome['announcement_badge_ar'] = $data['announcement_badge_ar'] ?? null;
        \App\Models\Website\WebsiteSetting::putValue('header_chrome', $chrome);
        $this->cms->clearCache();

        return back()->with('success', 'Badge updated.');
    }
}
