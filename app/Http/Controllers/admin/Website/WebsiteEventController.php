<?php

namespace App\Http\Controllers\admin\Website;

use App\Http\Controllers\Controller;
use App\Models\Website\WebsiteEvent;
use App\Services\Website\WebsiteContentService;
use App\Services\Website\WebsiteMediaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebsiteEventController extends Controller
{
    public function __construct(
        protected WebsiteContentService $cms,
        protected WebsiteMediaService $media,
    ) {}

    public function index()
    {
        return Inertia::render('Admin/theme1/Website/Events/Index', [
            'events' => WebsiteEvent::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/theme1/Website/Events/Form', ['event' => null]);
    }

    public function store(Request $request)
    {
        $this->save(new WebsiteEvent, $request);

        return redirect()->route('admin.website.events.index')->with('success', 'تمت الإضافة.');
    }

    public function edit(WebsiteEvent $event)
    {
        return Inertia::render('Admin/theme1/Website/Events/Form', ['event' => $event]);
    }

    public function update(Request $request, WebsiteEvent $event)
    {
        $this->save($event, $request);

        return redirect()->route('admin.website.events.index')->with('success', 'تم التحديث.');
    }

    public function destroy(WebsiteEvent $event)
    {
        $event->delete();
        $this->cms->clearCache();

        return redirect()->route('admin.website.events.index')->with('success', 'تم الحذف.');
    }

    protected function save(WebsiteEvent $model, Request $request): void
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:120',
            'date' => 'nullable|string|max:64',
            'date_short' => 'nullable|string|max:32',
            'audience' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'cta' => 'nullable|string|max:120',
            'href' => 'nullable|string|max:2048',
            'is_open_day' => 'boolean',
            'limited_seats_label' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'image_src' => 'nullable|string|max:2048',
            'image_alt' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:5120',
        ]);
        if (empty($data['slug'])) {
            $data['slug'] = \Illuminate\Support\Str::slug($data['title']);
        }
        unset($data['image_src'], $data['image_alt']);
        $model->fill($data);
        $this->media->applyModelImage($model, $request, [], $data['title']);
        $model->save();
        $this->cms->clearCache();
    }
}
