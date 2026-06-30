<?php

namespace App\Http\Controllers\admin\Website;

use App\Http\Controllers\Controller;
use App\Models\Website\WebsiteTestimonial;
use App\Services\Website\WebsiteContentService;
use App\Services\Website\WebsiteMediaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebsiteTestimonialController extends Controller
{
    public function __construct(
        protected WebsiteContentService $cms,
        protected WebsiteMediaService $media,
    ) {}

    public function index()
    {
        return Inertia::render('Admin/theme1/Website/Testimonials/Index', [
            'testimonials' => WebsiteTestimonial::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/theme1/Website/Testimonials/Form', ['testimonial' => null]);
    }

    public function store(Request $request)
    {
        $this->save(new WebsiteTestimonial, $request);

        return redirect()->route('admin.website.testimonials.index')->with('success', 'تمت الإضافة.');
    }

    public function edit(WebsiteTestimonial $testimonial)
    {
        return Inertia::render('Admin/theme1/Website/Testimonials/Form', ['testimonial' => $testimonial]);
    }

    public function update(Request $request, WebsiteTestimonial $testimonial)
    {
        $this->save($testimonial, $request);

        return redirect()->route('admin.website.testimonials.index')->with('success', 'تم التحديث.');
    }

    public function destroy(WebsiteTestimonial $testimonial)
    {
        $testimonial->delete();
        $this->cms->clearCache();

        return redirect()->route('admin.website.testimonials.index')->with('success', 'تم الحذف.');
    }

    protected function save(WebsiteTestimonial $model, Request $request): void
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'required|string|max:120',
            'role_type' => 'nullable|in:parent,student,teacher,alumni',
            'quote' => 'required|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'photo_src' => 'nullable|string|max:2048',
            'photo_alt' => 'nullable|string|max:255',
            'photo' => 'nullable|image|max:5120',
        ]);
        unset($data['photo_src'], $data['photo_alt']);
        $model->fill($data);
        $this->media->applyModelImage($model, $request, [
            'file' => 'photo',
            'src' => 'photo_src',
            'alt' => 'photo_alt',
            'media_id_column' => 'photo_media_id',
            'src_column' => 'photo_src',
            'alt_column' => 'photo_alt',
        ], $data['name']);
        $model->save();
        $this->cms->clearCache();
    }
}
