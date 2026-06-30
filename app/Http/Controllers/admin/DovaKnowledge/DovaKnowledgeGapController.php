<?php

namespace App\Http\Controllers\admin\DovaKnowledge;

use App\Http\Controllers\Controller;
use App\Models\DovaKnowledgeGap;
use App\Services\Dova\DovaFaqCategoryService;
use App\Services\Dova\DovaKnowledgeGapDetectionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DovaKnowledgeGapController extends Controller
{
    public function index(Request $request, DovaKnowledgeGapDetectionService $gaps, DovaFaqCategoryService $categories)
    {
        $categories->ensureDefaults();

        return Inertia::render('Admin/theme1/DovaKnowledge/Gaps/Index', [
            'gaps' => $gaps->listGaps($request->only(['priority', 'portal', 'role', 'category', 'status'])),
            'categories' => $categories->listForSelect(),
            'filters' => $request->only(['priority', 'portal', 'role', 'category', 'status']),
        ]);
    }

    public function sync(DovaKnowledgeGapDetectionService $gaps)
    {
        $count = $gaps->syncFromQueryLogs();

        return back()->with('success', "تم تحديث {$count} فجوة معرفية.");
    }

    public function dismiss(DovaKnowledgeGap $gap)
    {
        $gap->update(['status' => DovaKnowledgeGap::STATUS_DISMISSED]);

        return back()->with('success', 'تم تجاهل الفجوة.');
    }

    public function createFaq(DovaKnowledgeGap $gap)
    {
        return redirect()->route('admin.dova-knowledge.faqs.create', ['gap_id' => $gap->id]);
    }
}
