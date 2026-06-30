<?php

namespace App\Http\Controllers\admin\DovaKnowledge;

use App\Http\Controllers\Controller;
use App\Models\DovaKnowledgeGap;
use App\Services\Dova\DovaUnansweredResolutionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DovaUnansweredController extends Controller
{
    public function index(Request $request, DovaUnansweredResolutionService $resolution)
    {
        return Inertia::render('Admin/theme1/DovaKnowledge/Unanswered', $resolution->pageData(
            $request->only(['portal', 'priority', 'status']),
        ));
    }

    public function show(DovaKnowledgeGap $gap, DovaUnansweredResolutionService $resolution)
    {
        return response()->json($resolution->showGap($gap));
    }

    public function saveDraft(Request $request, DovaKnowledgeGap $gap, DovaUnansweredResolutionService $resolution)
    {
        $data = $this->validated($request);
        $faq = $resolution->saveDraft($gap, $data, $request->user());

        return back()->with('success', 'تم حفظ المسودة بنجاح.')
            ->with('savedFaqId', $faq->id);
    }

    public function publish(Request $request, DovaKnowledgeGap $gap, DovaUnansweredResolutionService $resolution)
    {
        $data = $this->validated($request);
        $faq = $resolution->publishFaq($gap, $data, $request->user());

        return back()->with('success', 'تم نشر السؤال الشائع وتعلّم دوفا الإجابة فوراً.')
            ->with('publishedFaqId', $faq->id);
    }

    public function ignore(DovaKnowledgeGap $gap, DovaUnansweredResolutionService $resolution)
    {
        $resolution->ignore($gap);

        return back()->with('success', 'تم تجاهل السؤال.');
    }

    public function sync(DovaUnansweredResolutionService $resolution)
    {
        $count = $resolution->syncGaps();

        return back()->with('success', "تم تحديث {$count} سؤال بلا إجابة.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'question_en' => ['required', 'string', 'max:1000'],
            'question_ar' => ['nullable', 'string', 'max:1000'],
            'answer_en' => ['required', 'string', 'max:10000'],
            'answer_ar' => ['nullable', 'string', 'max:10000'],
            'category_id' => ['nullable', 'integer', 'exists:dova_faq_categories,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ]);
    }
}
