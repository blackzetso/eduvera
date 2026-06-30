<?php

namespace App\Http\Controllers\admin\Website;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ValidatesBilingualFields;
use App\Models\Website\WebsitePost;
use App\Services\Website\WebsiteContentService;
use App\Services\Website\WebsiteMediaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebsitePostController extends Controller
{
    use ValidatesBilingualFields;

    public function __construct(
        protected WebsiteContentService $cms,
        protected WebsiteMediaService $media,
    ) {}

    public function index(Request $request)
    {
        $type = $request->query('type', 'news');

        return Inertia::render('Admin/theme1/Website/Posts/Index', [
            'type' => $type,
            'posts' => WebsitePost::query()->where('type', $type)->orderBy('sort_order')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Admin/theme1/Website/Posts/Form', [
            'post' => null,
            'type' => $request->query('type', 'news'),
            'publicPreviewUrl' => null,
        ]);
    }

    public function store(Request $request)
    {
        $post = new WebsitePost;
        $post->type = $request->input('type', 'news');
        $this->save($post, $request);

        return redirect()->route('admin.website.posts.index', ['type' => $post->type])->with('success', 'تمت الإضافة.');
    }

    public function edit(WebsitePost $post)
    {
        $post->load('imageMedia');
        $slug = $post->slug ?: \Illuminate\Support\Str::slug($post->title);

        return Inertia::render('Admin/theme1/Website/Posts/Form', [
            'post' => $post,
            'type' => $post->type,
            'publicPreviewUrl' => route('school-talent.article', ['type' => $post->type, 'slug' => $slug]),
        ]);
    }

    public function update(Request $request, WebsitePost $post)
    {
        $this->save($post, $request);

        return redirect()->route('admin.website.posts.index', ['type' => $post->type])->with('success', 'تم التحديث.');
    }

    public function destroy(WebsitePost $post)
    {
        $type = $post->type;
        $post->delete();
        $this->cms->clearCache();

        return redirect()->route('admin.website.posts.index', ['type' => $type])->with('success', 'تم الحذف.');
    }

    protected function save(WebsitePost $model, Request $request): void
    {
        $data = $request->validate(array_merge([
            'type' => 'required|in:news,blog',
            'slug' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:120',
            'published_at' => 'nullable|string|max:64',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'image_src' => 'nullable|string|max:2048',
            'image_alt' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:5120',
        ], $this->bilingualFieldRules('title', 255), $this->bilingualFieldRules('summary', 65535, true), $this->bilingualFieldRules('content', 65535, true)));
        if (empty($data['slug'])) {
            $data['slug'] = \Illuminate\Support\Str::slug($data['title'] ?: $data['title_ar']);
        }

        unset($data['image_src'], $data['image_alt']);

        $model->fill($data);
        $this->media->applyModelImage($model, $request, [], $data['title'] ?: $data['title_ar']);
        $model->save();
        $this->cms->clearCache();
    }
}
