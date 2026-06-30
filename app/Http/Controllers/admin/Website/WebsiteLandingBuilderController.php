<?php

namespace App\Http\Controllers\admin\Website;

use App\Http\Controllers\Controller;
use App\Models\Website\WebsiteLandingSection;
use App\Models\Website\WebsiteLandingSectionRevision;
use App\Services\Website\WebsiteLandingBuilderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteLandingBuilderController extends Controller
{
    public function __construct(
        protected WebsiteLandingBuilderService $builder,
    ) {}

    public function index(): Response
    {
        $data = $this->builder->sectionsForAdmin();

        return Inertia::render('Admin/theme1/Website/LandingBuilder/Index', $data);
    }

    public function edit(WebsiteLandingSection $section): Response
    {
        $data = $this->builder->sectionsForAdmin();
        $meta = config('website-landing-blocks.library.'.$section->block_type, []);

        return Inertia::render('Admin/theme1/Website/LandingBuilder/Edit', [
            ...$data,
            'editing' => array_merge(
                $section->toPublicArray(),
                ['library_label' => $meta['label'] ?? $section->block_type]
            ),
        ]);
    }

    public function preview(Request $request): Response
    {
        $device = in_array($request->query('device'), ['desktop', 'tablet', 'mobile'], true)
            ? $request->query('device')
            : 'desktop';

        return Inertia::render('Admin/theme1/Website/LandingBuilder/Preview', [
            'device' => $device,
            'previewUrl' => url('/?landing_preview=1'),
        ]);
    }

    public function storeSection(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'block_type' => 'required|string',
            'admin_name' => 'nullable|string|max:120',
            'custom_subtype' => 'nullable|string',
        ]);

        $this->builder->addSection(
            $data['block_type'],
            $data['admin_name'] ?? null,
            $data['custom_subtype'] ?? null,
        );

        return back()->with('success', 'Section added.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order' => 'required|array',
            'order.*' => 'required|uuid',
        ]);

        $this->builder->reorder($data['order']);

        return back()->with('success', 'Order saved.');
    }

    public function update(Request $request, WebsiteLandingSection $section): RedirectResponse
    {
        $data = $request->validate([
            'admin_name' => 'sometimes|string|max:120',
            'anchor_id' => 'nullable|string|max:64',
            'is_enabled' => 'sometimes|boolean',
            'is_visible' => 'sometimes|boolean',
            'settings' => 'sometimes|array',
            'content' => 'sometimes|array',
            'show_desktop' => 'sometimes|boolean',
            'show_tablet' => 'sometimes|boolean',
            'show_mobile' => 'sometimes|boolean',
            'scheduled_starts_at' => 'nullable|date',
            'scheduled_ends_at' => 'nullable|date|after_or_equal:scheduled_starts_at',
        ]);

        $this->builder->updateSection($section, $data);

        return back()->with('success', 'Section updated.');
    }

    public function duplicate(WebsiteLandingSection $section): RedirectResponse
    {
        $copy = $this->builder->duplicateSection($section);

        return redirect()
            ->route('admin.website.landing-builder.edit', $copy)
            ->with('success', 'Section duplicated.');
    }

    public function destroy(WebsiteLandingSection $section): RedirectResponse
    {
        $this->builder->deleteSection($section);

        return redirect()
            ->route('admin.website.landing-builder.index')
            ->with('success', 'Section removed.');
    }

    public function publish(): RedirectResponse
    {
        $this->builder->publish();

        return back()->with('success', 'Landing page published.');
    }

    public function setStatus(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|in:draft,published,archived',
        ]);

        $this->builder->setPageStatus($data['status']);

        return back()->with('success', 'Status updated.');
    }

    public function restoreRevision(WebsiteLandingSectionRevision $revision): RedirectResponse
    {
        $this->builder->restoreRevision($revision);

        return redirect()
            ->route('admin.website.landing-builder.index')
            ->with('success', 'Revision restored.');
    }

    public function saveRevision(Request $request): RedirectResponse
    {
        $request->validate(['note' => 'nullable|string|max:255']);
        $this->builder->createRevision(null, $request->input('note'));

        return back()->with('success', 'Revision saved.');
    }
}
