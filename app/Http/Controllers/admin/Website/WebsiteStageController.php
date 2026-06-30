<?php

namespace App\Http\Controllers\admin\Website;

use App\Http\Controllers\Controller;
use App\Models\Website\WebsiteStage;
use App\Services\Website\WebsiteContentService;
use App\Services\Website\WebsiteMediaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebsiteStageController extends Controller
{
    public function __construct(
        protected WebsiteContentService $cms,
        protected WebsiteMediaService $media,
    ) {}

    public function index()
    {
        return Inertia::render('Admin/theme1/Website/Stages/Index', [
            'stages' => WebsiteStage::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/theme1/Website/Stages/Form', ['stage' => null]);
    }

    public function store(Request $request)
    {
        $this->saveStage(new WebsiteStage, $request);

        return redirect()->route('admin.website.stages.index')->with('success', 'تم إضافة المرحلة.');
    }

    public function edit(WebsiteStage $stage)
    {
        return Inertia::render('Admin/theme1/Website/Stages/Form', [
            'stage' => $stage->load('imageMedia'),
        ]);
    }

    public function update(Request $request, WebsiteStage $stage)
    {
        $this->saveStage($stage, $request);

        return redirect()->route('admin.website.stages.index')->with('success', 'تم تحديث المرحلة.');
    }

    public function destroy(WebsiteStage $stage)
    {
        $stage->delete();
        $this->cms->clearCache();

        return redirect()->route('admin.website.stages.index')->with('success', 'تم حذف المرحلة.');
    }

    protected function saveStage(WebsiteStage $stage, Request $request): void
    {
        $data = $request->validate([
            'slug' => 'required|string|max:120',
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string',
            'age_range' => 'nullable|string',
            'tagline' => 'nullable|string',
            'tone' => 'nullable|string',
            'student_count' => 'nullable|integer',
            'class_size' => 'nullable|integer',
            'key_skills' => 'nullable|array',
            'payload' => 'nullable|array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'image' => 'nullable|image|max:5120',
        ]);

        if (! empty($data['payload'])) {
            $data['payload'] = array_merge($stage->payload ?? [], $data['payload']);
        }

        $stage->fill($data);
        if ($request->hasFile('image')) {
            $media = $this->media->store($request->file('image'), $data['title']);
            $stage->image_media_id = $media->id;
            $stage->image_src = $media->url();
            $stage->image_alt = $media->alt;
        }
        $stage->save();
        $this->cms->clearCache();
    }
}
