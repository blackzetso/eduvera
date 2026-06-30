<?php

namespace App\Http\Controllers\admin\DovaKnowledge;

use App\Http\Controllers\Controller;
use App\Models\DovaKnowledgeRecord;
use App\Models\DovaKnowledgeSource;
use App\Services\Dova\DovaFaqAnalyticsService;
use App\Services\Dova\DovaKnowledgeAnalyticsService;
use App\Services\Dova\DovaLLMService;
use App\Services\Dova\DovaKnowledgeExplorerService;
use App\Services\Dova\DovaKnowledgeSyncService;
use App\Support\Dova\DovaKnowledgeService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DovaKnowledgeController extends Controller
{
    public function dashboard(DovaKnowledgeAnalyticsService $analytics, DovaKnowledgeSyncService $sync)
    {
        $sync->ensureSources();

        return Inertia::render('Admin/theme1/DovaKnowledge/Dashboard', [
            'overview' => $analytics->dashboardOverview(),
            'sources' => array_slice($analytics->sourcesList(), 0, 6),
        ]);
    }

    public function sources(DovaKnowledgeAnalyticsService $analytics, DovaKnowledgeSyncService $sync)
    {
        $sync->ensureSources();

        return Inertia::render('Admin/theme1/DovaKnowledge/Sources', [
            'sources' => $analytics->sourcesList(),
        ]);
    }

    public function toggleSource(DovaKnowledgeSource $source)
    {
        $source->update(['enabled' => ! $source->enabled]);

        return back()->with('success', $source->enabled ? 'تم تفعيل المصدر.' : 'تم تعطيل المصدر.');
    }

    public function reindexSource(DovaKnowledgeSource $source, DovaKnowledgeSyncService $sync)
    {
        $result = $sync->syncSource($source->slug);

        return back()->with('success', "تمت إعادة الفهرسة — {$result['records']} سجل.");
    }

    public function sourceRecords(DovaKnowledgeSource $source, Request $request)
    {
        $records = DovaKnowledgeRecord::query()
            ->where('source_slug', $source->slug)
            ->when($request->filled('locale'), fn ($q) => $q->where('locale', $request->string('locale')->toString()))
            ->orderBy('record_key')
            ->paginate(20)
            ->through(fn (DovaKnowledgeRecord $r) => [
                'id' => $r->id,
                'recordKey' => $r->record_key,
                'title' => $r->title,
                'content' => mb_substr($r->content, 0, 300),
                'locale' => $r->locale,
                'indexedAt' => $r->indexed_at?->format('Y-m-d H:i') ?? '—',
            ]);

        return Inertia::render('Admin/theme1/DovaKnowledge/SourceRecords', [
            'source' => [
                'id' => $source->id,
                'slug' => $source->slug,
                'name' => $source->name_ar,
                'recordCount' => $source->record_count,
                'lastSyncedAt' => $source->last_synced_at?->format('Y-m-d H:i') ?? '—',
            ],
            'records' => $records,
            'filters' => ['locale' => $request->string('locale')->toString()],
        ]);
    }

    public function syncCenter(DovaKnowledgeSyncService $sync)
    {
        $sync->ensureSources();

        $sources = DovaKnowledgeSource::query()->orderBy('slug')->get();

        return Inertia::render('Admin/theme1/DovaKnowledge/Sync', [
            'lastSync' => $sources->max('last_synced_at')?->format('Y-m-d H:i') ?? '—',
            'groups' => [
                ['key' => 'cms', 'label' => 'مزامنة CMS', 'description' => 'كل محتوى الموقع من قاعدة البيانات'],
                ['key' => 'website', 'label' => 'مزامنة الموقع', 'description' => 'معلومات المدرسة، البطل، التنقل'],
                ['key' => 'faq', 'label' => 'مزامنة FAQ', 'description' => 'الأسئلة الشائعة'],
                ['key' => 'school_info', 'label' => 'مزامنة معلومات المدرسة', 'description' => 'الاسم، العنوان، التواصل'],
                ['key' => 'admissions', 'label' => 'مزامنة القبول', 'description' => 'متطلبات القبول والسياسات'],
                ['key' => 'everything', 'label' => 'مزامنة الكل', 'description' => 'إعادة فهرسة جميع المصادر المفعّلة'],
            ],
        ]);
    }

    public function runSync(string $group, DovaKnowledgeSyncService $sync)
    {
        $result = $sync->syncGroup($group);

        return back()->with('success', "تمت المزامنة — {$result['records']} سجل من {$result['synced']} مصدر.");
    }

    public function explorer(Request $request, DovaKnowledgeExplorerService $explorer)
    {
        $query = trim((string) $request->input('q', ''));

        return Inertia::render('Admin/theme1/DovaKnowledge/Explorer', [
            'query' => $query,
            'results' => $query !== '' ? $explorer->search($query) : [],
        ]);
    }

    public function testing(DovaLLMService $llm)
    {
        return Inertia::render('Admin/theme1/DovaKnowledge/Testing', [
            'result' => null,
            'aiEnabled' => $llm->isEnabled(),
            'aiModel' => config('dova-ai.model'),
        ]);
    }

    public function runTest(Request $request, DovaKnowledgeService $knowledge, DovaLLMService $llm)
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'locale' => ['nullable', 'in:en,ar'],
        ]);

        $locale = $validated['locale'] ?? 'en';
        $ctx = ['portal' => 'admin', 'role' => 'admin'];

        $started = microtime(true);
        $answer = $knowledge->answer($validated['question'], $locale);
        $knowledgeMs = (int) round((microtime(true) - $started) * 1000);

        $rawText = implode("\n\n", array_filter([
            $answer['introduction'] ?? '',
            $answer['explanation'] ?? '',
            $answer['footer'] ?? '',
        ]));

        $enhanced = null;
        $finalText = $rawText;
        $aiDebug = null;
        $usedLlm = false;
        $llmFallback = true;

        if ($answer['matched'] ?? false) {
            $llmStarted = microtime(true);
            $enhanced = $llm->enhanceKnowledge(
                $validated['question'],
                $answer,
                $locale,
                $ctx,
                $request->user()?->id,
            );
            $llmMs = (int) round((microtime(true) - $llmStarted) * 1000);
            $finalText = $enhanced['text'] ?? $rawText;
            $usedLlm = (bool) ($enhanced['used_llm'] ?? false);
            $llmFallback = (bool) ($enhanced['fallback'] ?? true);
            $aiDebug = $enhanced['aiDebug'] ?? null;
            if ($aiDebug) {
                $aiDebug['responseMs'] = $aiDebug['responseMs'] ?? $llmMs;
            }
        }

        return Inertia::render('Admin/theme1/DovaKnowledge/Testing', [
            'result' => [
                'question' => $validated['question'],
                'matched' => (bool) ($answer['matched'] ?? false),
                'source' => $answer['source'] ?? null,
                'record' => $answer['record'] ?? null,
                'confidence' => $answer['confidence'] ?? 0,
                'matchedContent' => $answer['matchedText'] ?? null,
                'knowledgeMs' => $knowledgeMs,
                'rawAnswer' => $rawText ?: '—',
                'finalAnswer' => $finalText ?: ($answer['matched'] ? '—' : $llm->noKnowledgeMessage($locale)),
                'usedLlm' => $usedLlm,
                'llmFallback' => $llmFallback,
                'aiDebug' => $aiDebug,
            ],
            'question' => $validated['question'],
            'locale' => $locale,
            'aiEnabled' => $llm->isEnabled(),
            'aiModel' => config('dova-ai.model'),
        ]);
    }

    public function analytics(DovaKnowledgeAnalyticsService $analytics, DovaFaqAnalyticsService $faqAnalytics)
    {
        return Inertia::render('Admin/theme1/DovaKnowledge/Analytics', [
            'overview' => $analytics->dashboardOverview(),
            'charts' => $analytics->chartData(),
            'faqAnalytics' => $faqAnalytics->faqAnalytics(),
        ]);
    }
}
