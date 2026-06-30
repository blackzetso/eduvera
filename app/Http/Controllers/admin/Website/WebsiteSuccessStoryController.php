<?php

namespace App\Http\Controllers\admin\Website;

use App\Http\Controllers\Controller;
use App\Models\Website\WebsiteSuccessStory;
use App\Services\Website\WebsiteContentService;
use App\Services\Website\WebsiteMediaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebsiteSuccessStoryController extends Controller
{
    public function __construct(
        protected WebsiteContentService $cms,
        protected WebsiteMediaService $media,
    ) {}

    public function index()
    {
        return Inertia::render('Admin/theme1/Website/SuccessStories/Index', [
            'stories' => WebsiteSuccessStory::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/theme1/Website/SuccessStories/Form', ['story' => null]);
    }

    public function store(Request $request)
    {
        $this->save(new WebsiteSuccessStory, $request);

        return redirect()->route('admin.website.success-stories.index')->with('success', 'تمت الإضافة.');
    }

    public function edit(WebsiteSuccessStory $success_story)
    {
        return Inertia::render('Admin/theme1/Website/SuccessStories/Form', ['story' => $success_story]);
    }

    public function update(Request $request, WebsiteSuccessStory $success_story)
    {
        $this->save($success_story, $request);

        return redirect()->route('admin.website.success-stories.index')->with('success', 'تم التحديث.');
    }

    public function destroy(WebsiteSuccessStory $success_story)
    {
        $success_story->delete();
        $this->cms->clearCache();

        return redirect()->route('admin.website.success-stories.index')->with('success', 'تم الحذف.');
    }

    protected function save(WebsiteSuccessStory $model, Request $request): void
    {
        $data = $request->validate([
            'student_name' => 'required|string|max:255',
            'achievement' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:120',
            'story' => 'nullable|string',
            'stat_value' => 'nullable|string|max:64',
            'stat_label' => 'nullable|string|max:120',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'image_src' => 'nullable|string|max:2048',
            'image_alt' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:5120',
        ]);
        unset($data['image_src'], $data['image_alt']);
        $model->fill($data);
        $this->media->applyModelImage($model, $request, [], $data['achievement'] ?: $data['student_name']);
        $model->save();
        $this->cms->clearCache();
    }
}
