<?php

namespace App\Http\Controllers\admin\Website;

use App\Http\Controllers\Controller;
use App\Models\Website\WebsiteMedia;
use App\Services\Website\WebsiteMediaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebsiteMediaController extends Controller
{
    public function __construct(protected WebsiteMediaService $media) {}

    public function index()
    {
        $media = WebsiteMedia::query()->latest()->paginate(24);
        $media->getCollection()->transform(function ($m) {
            $this->media->mirrorToPublicWebRoot($m->path);

            return array_merge($m->toArray(), [
                'url' => $m->url(),
                'full_url' => $m->absoluteUrl(),
            ]);
        });

        return Inertia::render('Admin/theme1/Website/Media/Index', [
            'media' => $media,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|image|max:10240',
            'alt' => 'nullable|string|max:255',
        ]);

        $this->media->store($request->file('file'), $request->input('alt'));

        return back()->with('success', 'تم رفع الملف.');
    }

    public function destroy(WebsiteMedia $medium)
    {
        $this->media->delete($medium);

        return back()->with('success', 'تم حذف الملف.');
    }

    public function picker()
    {
        return response()->json([
            'media' => WebsiteMedia::query()->latest()->limit(50)->get()->map(function ($m) {
                $this->media->mirrorToPublicWebRoot($m->path);

                return [
                    'id' => $m->id,
                    'url' => $m->url(),
                    'full_url' => $m->absoluteUrl(),
                    'alt' => $m->alt,
                    'filename' => $m->filename,
                ];
            }),
        ]);
    }
}
