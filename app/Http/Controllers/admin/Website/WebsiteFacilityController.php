<?php

namespace App\Http\Controllers\admin\Website;

use App\Http\Controllers\Controller;
use App\Models\Website\WebsiteFacility;
use App\Services\Website\WebsiteContentService;
use App\Services\Website\WebsiteMediaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebsiteFacilityController extends Controller
{
    public function __construct(
        protected WebsiteContentService $cms,
        protected WebsiteMediaService $media,
    ) {}

    public function index()
    {
        return Inertia::render('Admin/theme1/Website/Facilities/Index', [
            'facilities' => WebsiteFacility::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/theme1/Website/Facilities/Form', ['facility' => null]);
    }

    public function store(Request $request)
    {
        $this->save(new WebsiteFacility, $request);

        return redirect()->route('admin.website.facilities.index')->with('success', 'تمت الإضافة.');
    }

    public function edit(WebsiteFacility $facility)
    {
        return Inertia::render('Admin/theme1/Website/Facilities/Form', ['facility' => $facility]);
    }

    public function update(Request $request, WebsiteFacility $facility)
    {
        $this->save($facility, $request);

        return redirect()->route('admin.website.facilities.index')->with('success', 'تم التحديث.');
    }

    public function destroy(WebsiteFacility $facility)
    {
        $facility->delete();
        $this->cms->clearCache();

        return redirect()->route('admin.website.facilities.index')->with('success', 'تم الحذف.');
    }

    protected function save(WebsiteFacility $model, Request $request): void
    {
        $data = $request->validate([
            'icon' => 'nullable|string|max:80',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'benefit' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'image_src' => 'nullable|string|max:2048',
            'image_alt' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:5120',
        ]);
        unset($data['image_src'], $data['image_alt']);
        $model->fill($data);
        $this->media->applyModelImage($model, $request, [], $data['name']);
        $model->save();
        $this->cms->clearCache();
    }
}
