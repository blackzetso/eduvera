<?php

namespace App\Http\Controllers\admin\DovaKnowledge;

use App\Http\Controllers\Controller;
use App\Models\DovaFaq;
use App\Services\Dova\DovaFaqAnalyticsService;
use App\Services\Dova\DovaFaqCategoryService;
use App\Services\Dova\DovaFaqGovernanceService;
use App\Services\Dova\DovaFaqService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DovaFaqController extends Controller
{
    public function dashboard(DovaFaqAnalyticsService $analytics)
    {
        return Inertia::render('Admin/theme1/DovaKnowledge/Faq/Dashboard', [
            'stats' => $analytics->dashboard(),
            'faqAnalytics' => $analytics->faqAnalytics(),
        ]);
    }

    public function index(Request $request, DovaFaqAnalyticsService $analytics, DovaFaqCategoryService $categories, DovaFaqGovernanceService $governance)
    {
        return Inertia::render('Admin/theme1/DovaKnowledge/Faq/Index', [
            'faqs' => $analytics->listFaqs($request->only([
                'status', 'category_id', 'source', 'search',
                'knowledge_status', 'owner_user_id', 'review_filter',
            ])),
            'categories' => $categories->listForSelect(),
            'owners' => $governance->listOwners(),
            'filters' => $request->only([
                'status', 'category_id', 'source', 'search',
                'knowledge_status', 'owner_user_id', 'review_filter',
            ]),
        ]);
    }

    public function create(Request $request, DovaFaqCategoryService $categories, DovaFaqService $faqs, DovaFaqGovernanceService $governance)
    {
        $prefill = null;
        if ($request->filled('gap_id')) {
            $gap = \App\Models\DovaKnowledgeGap::query()->find($request->integer('gap_id'));
            if ($gap) {
                $prefill = $faqs->prefillFromGap($gap);
            }
        }

        return Inertia::render('Admin/theme1/DovaKnowledge/Faq/Form', [
            'faq' => null,
            'categories' => $categories->listForSelect(),
            'owners' => $governance->listOwners(),
            'reviewFrequencies' => $governance->reviewFrequencies(),
            'prefill' => $prefill,
        ]);
    }

    public function store(Request $request, DovaFaqService $faqs)
    {
        $data = $this->validated($request);
        $data['category_id'] = $this->resolveCategoryId($request, $data);

        $faqs->create($data, $request->user());

        return redirect()->route('admin.dova-knowledge.faqs.index')
            ->with('success', 'تم إنشاء السؤال بنجاح.');
    }

    public function edit(DovaFaq $faq, DovaFaqCategoryService $categories, DovaFaqGovernanceService $governance)
    {
        $faq->load(['category', 'creator', 'updater', 'knowledgeGap', 'owner']);

        return Inertia::render('Admin/theme1/DovaKnowledge/Faq/Form', [
            'faq' => [
                'id' => $faq->id,
                'question_en' => $faq->question_en,
                'question_ar' => $faq->question_ar,
                'answer_en' => $faq->answer_en,
                'answer_ar' => $faq->answer_ar,
                'category_id' => $faq->category_id,
                'tags' => $faq->tags ?? [],
                'status' => $faq->status,
                'source' => $faq->source,
                'knowledge_gap_id' => $faq->knowledge_gap_id,
                'owner_user_id' => $faq->owner_user_id,
                'review_frequency_days' => $faq->review_frequency_days,
                'last_reviewed_at' => $faq->last_reviewed_at?->format('Y-m-d H:i'),
                'next_review_due_at' => $faq->next_review_due_at?->format('Y-m-d H:i'),
                'knowledge_status' => $faq->knowledge_status,
                'knowledgeStatusLabel' => $governance->knowledgeStatusLabel($faq->knowledge_status),
                'ownerName' => $faq->owner?->name,
                'createdBy' => $faq->creator?->name,
                'updatedBy' => $faq->updater?->name,
                'createdAt' => $faq->created_at?->format('Y-m-d H:i'),
                'updatedAt' => $faq->updated_at?->format('Y-m-d H:i'),
            ],
            'categories' => $categories->listForSelect(),
            'owners' => $governance->listOwners(),
            'reviewFrequencies' => $governance->reviewFrequencies(),
            'prefill' => null,
        ]);
    }

    public function update(Request $request, DovaFaq $faq, DovaFaqService $faqs)
    {
        $data = $this->validated($request);
        $data['category_id'] = $this->resolveCategoryId($request, $data);

        $faqs->update($faq, $data, $request->user());

        return redirect()->route('admin.dova-knowledge.faqs.index')
            ->with('success', 'تم تحديث السؤال.');
    }

    public function destroy(DovaFaq $faq, DovaFaqService $faqs)
    {
        $faqs->delete($faq);

        return back()->with('success', 'تم حذف السؤال.');
    }

    public function submitReview(DovaFaq $faq, Request $request, DovaFaqService $faqs)
    {
        $faqs->submitForReview($faq, $request->user());

        return back()->with('success', 'تم إرسال السؤال للمراجعة.');
    }

    public function publish(DovaFaq $faq, Request $request, DovaFaqService $faqs)
    {
        $faqs->publish($faq, $request->user());

        return back()->with('success', 'تم نشر السؤال وإعادة فهرسة المعرفة تلقائياً.');
    }

    public function archive(DovaFaq $faq, Request $request, DovaFaqService $faqs)
    {
        $faqs->archive($faq, $request->user());

        return back()->with('success', 'تم أرشفة السؤال.');
    }

    public function completeReview(DovaFaq $faq, Request $request, DovaFaqGovernanceService $governance)
    {
        $governance->completeReview($faq, $request->user());

        if ($faq->fresh()->status === DovaFaq::STATUS_PUBLISHED) {
            app(\App\Services\Dova\DovaKnowledgeSyncService::class)->syncSource('faq');
        }

        return back()->with('success', 'تمت مراجعة المعرفة وتحديث جدول المراجعة.');
    }

    public function deprecate(DovaFaq $faq, Request $request, DovaFaqGovernanceService $governance)
    {
        $governance->deprecate($faq, $request->user());

        return back()->with('success', 'تم وضع السؤال كمعرفة مهملة.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'question_en' => ['required', 'string', 'max:1000'],
            'question_ar' => ['nullable', 'string', 'max:1000'],
            'answer_en' => ['required', 'string', 'max:5000'],
            'answer_ar' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'integer', 'exists:dova_faq_categories,id'],
            'custom_category' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'status' => ['nullable', 'in:draft,review,published,archived'],
            'source' => ['nullable', 'string', 'max:32'],
            'knowledge_gap_id' => ['nullable', 'integer', 'exists:dova_knowledge_gaps,id'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'review_frequency_days' => ['nullable', 'integer', 'min:7', 'max:730'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveCategoryId(Request $request, array $data): ?int
    {
        if (! empty($data['category_id'])) {
            return (int) $data['category_id'];
        }

        $custom = trim((string) $request->input('custom_category', ''));
        if ($custom === '') {
            return null;
        }

        return app(DovaFaqCategoryService::class)->findOrCreateCustom($custom)->id;
    }
}
