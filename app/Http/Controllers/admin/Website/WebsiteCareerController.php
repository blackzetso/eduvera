<?php

namespace App\Http\Controllers\admin\Website;

use App\Http\Controllers\Controller;
use App\Models\Website\WebsiteCareer;
use App\Services\Website\WebsiteContentService;
use App\Services\Website\WebsiteMediaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebsiteCareerController extends Controller
{
    public function __construct(
        protected WebsiteContentService $cms,
        protected WebsiteMediaService $media,
    ) {}

    public function index()
    {
        return Inertia::render('Admin/theme1/Website/Careers/Index', [
            'careers' => WebsiteCareer::query()->orderBy('sort_order')->get(),
            'teacherRecruitment' => \App\Models\Website\WebsiteSetting::getValue('teacher_recruitment', []),
        ]);
    }

    public function updateRecruitment(Request $request)
    {
        $data = $request->validate([
            'teacherRecruitment' => 'required|array',
            'recruitment_image' => 'nullable|image|max:5120',
        ]);

        $block = $data['teacherRecruitment'];
        if ($request->hasFile('recruitment_image')) {
            $media = $this->media->store($request->file('recruitment_image'), 'Teacher recruitment');
            $block['image'] = $media->toImageRef('Teacher recruitment');
            $block['image']['src'] = $media->absoluteUrl();
        } elseif (! empty($block['image']['src'])) {
            $block['image']['src'] = $this->media->resolvePublicSrc($block['image']['src']);
        }

        \App\Models\Website\WebsiteSetting::putValue('teacher_recruitment', $block);
        $this->cms->clearCache();

        return back()->with('success', 'تم حفظ قسم التوظيف.');
    }

    public function create()
    {
        return Inertia::render('Admin/theme1/Website/Careers/Form', ['career' => null]);
    }

    public function store(Request $request)
    {
        $this->save(new WebsiteCareer, $request);

        return redirect()->route('admin.website.careers.index')->with('success', 'تمت الإضافة.');
    }

    public function edit(WebsiteCareer $career)
    {
        return Inertia::render('Admin/theme1/Website/Careers/Form', ['career' => $career]);
    }

    public function update(Request $request, WebsiteCareer $career)
    {
        $this->save($career, $request);

        return redirect()->route('admin.website.careers.index')->with('success', 'تم التحديث.');
    }

    public function destroy(WebsiteCareer $career)
    {
        $career->delete();
        $this->cms->clearCache();

        return redirect()->route('admin.website.careers.index')->with('success', 'تم الحذف.');
    }

    protected function save(WebsiteCareer $model, Request $request): void
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'department' => 'nullable|string|max:120',
            'type' => 'nullable|string|max:32',
            'description' => 'nullable|string',
            'apply_url' => 'nullable|string|max:2048',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);
        $model->fill($data)->save();
        $this->cms->clearCache();
    }
}
