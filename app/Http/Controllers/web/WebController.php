<?php

namespace App\Http\Controllers\web;

use inertia\inertia;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Website\WebsiteContentService;

class WebController extends Controller
{
    /**
     * Start Home Page
    **/
    /** School Talent — public marketing homepage */
    public function schoolTalentArticle(string $type, string $slug, WebsiteContentService $cms)
    {
        $content = $cms->forLanding();
        $items = $type === 'blog' ? ($content['blogPosts'] ?? []) : ($content['newsItems'] ?? []);
        $article = collect($items)->firstWhere('slug', $slug);

        if (! $article) {
            abort(404);
        }

        return Inertia::render('Marketing/SchoolTalentArticle', [
            'article' => $article,
            'type' => $type,
            'websiteContent' => $content,
            'websiteSeo' => [
                'meta_title' => $article['title'] ?? 'Article',
                'meta_description' => $article['excerpt'] ?? '',
            ],
        ]);
    }

    public function home(Request $request, WebsiteContentService $cms){
        $preview = $request->boolean('landing_preview')
            && $request->user()
            && $request->user()->isAdmin();

        return Inertia::render('Marketing/SchoolTalentLanding', [
            'websiteContent' => $cms->forLanding($preview),
            'websiteSeo' => $cms->isCmsActive()
                ? \App\Models\Website\WebsiteSetting::getValue('seo', [])
                : [],
            'canEditWebsiteCms' => (bool) ($request->user()?->isAdmin()),
            'landingPreview' => $preview,
            'previewDevice' => in_array($request->query('device'), ['desktop', 'tablet', 'mobile'], true)
                ? $request->query('device')
                : 'desktop',
        ]);
    }

    /** الصفحة التسويقية القديمة (قالب الطالب) */
    public function explore(){
        return Inertia::render('Student/Theme1/Index');
    }
    /**
     * End Home Page
    **/
    /**
     * Start Lessons Page
    **/
    public function lessons(){
        return Inertia::render('Student/Theme1/Lessons');
    }
    /**
     * End lessons Page
    **/
    /**
     * Start teachers Page
    **/
    public function teachers(){
        return Inertia::render('Student/Theme1/Teachers');
    }
    /**
     * End teachers Page
    **/
    /**
     * Start blog Page
    **/
    public function blog(){
        return Inertia::render('Student/Theme1/Blog');
    }
    /**
     * End blog Page
    **/

    /**
     * Show single lesson with files
     */
    public function showLesson($id)
    {
        $lesson = \App\Models\Lesson::with(['lectures.files', 'category'])
            ->findOrFail($id);

        return Inertia::render('Student/Theme1/LessonView', [
            'lesson' => $lesson,
        ]);
    }
    
}
