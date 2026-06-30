<?php

namespace App\Http\Controllers\admin\Website;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ValidatesBilingualFields;
use App\Models\Website\WebsiteNavLink;
use App\Services\Website\WebsiteContentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebsiteNavLinkController extends Controller
{
    use ValidatesBilingualFields;

    public function __construct(protected WebsiteContentService $cms) {}

    public function index()
    {
        return Inertia::render('Admin/theme1/Website/NavLinks/Index', [
            'links' => WebsiteNavLink::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(array_merge([
            'href' => 'required|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ], $this->bilingualFieldRules('label', 120)));
        WebsiteNavLink::query()->create($data);
        $this->cms->clearCache();

        return back()->with('success', 'Link added.');
    }

    public function update(Request $request, WebsiteNavLink $navLink)
    {
        $data = $request->validate(array_merge([
            'href' => 'required|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ], $this->bilingualFieldRules('label', 120)));
        $navLink->update($data);
        $this->cms->clearCache();

        return back()->with('success', 'Link updated.');
    }

    public function destroy(WebsiteNavLink $navLink)
    {
        $navLink->delete();
        $this->cms->clearCache();

        return back()->with('success', 'Link removed.');
    }

    public function reorder(Request $request)
    {
        $data = $request->validate(['order' => 'required|array', 'order.*' => 'integer']);
        foreach ($data['order'] as $i => $id) {
            WebsiteNavLink::query()->whereKey($id)->update(['sort_order' => $i + 1]);
        }
        $this->cms->clearCache();

        return back()->with('success', 'Order saved.');
    }
}
