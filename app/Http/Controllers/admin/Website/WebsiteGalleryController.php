<?php

namespace App\Http\Controllers\admin\Website;

use App\Http\Controllers\Controller;
use App\Models\Website\WebsiteGalleryItem;
use App\Services\Website\WebsiteContentService;
use App\Services\Website\WebsiteMediaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebsiteGalleryController extends Controller
{
    public function __construct(
        protected WebsiteContentService $cms,
        protected WebsiteMediaService $media,
    ) {}

    public function index()
    {
        return Inertia::render('Admin/theme1/Website/Gallery/Index', [
            'items' => WebsiteGalleryItem::query()->with('imageMedia')->orderBy('sort_order')->get(),
            'categories' => \App\Models\Website\WebsiteSetting::getValue(
                'gallery_categories',
                \App\Support\Website\WebsiteDefaultsRepository::load()['galleryCategories'] ?? []
            ),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category' => 'required|string|max:120',
            'alt' => 'nullable|string|max:255',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'image' => 'required|image|max:5120',
        ]);

        $media = $this->media->store($request->file('image'), $data['alt'] ?? 'Gallery');
        WebsiteGalleryItem::query()->create([
            'category' => $data['category'],
            'alt' => $data['alt'],
            'is_featured' => $data['is_featured'] ?? false,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
            'image_media_id' => $media->id,
            'src' => $media->url(),
        ]);
        $this->cms->clearCache();

        return back()->with('success', 'Image added.');
    }

    public function update(Request $request, WebsiteGalleryItem $gallery)
    {
        $data = $request->validate([
            'category' => 'required|string|max:120',
            'alt' => 'nullable|string|max:255',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'image' => 'nullable|image|max:5120',
        ]);

        $gallery->fill($data);
        if ($request->hasFile('image')) {
            $media = $this->media->store($request->file('image'), $data['alt'] ?? 'Gallery');
            $gallery->image_media_id = $media->id;
            $gallery->src = $media->url();
        }
        $gallery->save();
        $this->cms->clearCache();

        return back()->with('success', 'Updated.');
    }

    public function destroy(WebsiteGalleryItem $gallery)
    {
        $gallery->delete();
        $this->cms->clearCache();

        return back()->with('success', 'Removed.');
    }
}
