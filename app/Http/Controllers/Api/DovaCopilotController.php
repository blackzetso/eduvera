<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Dova\DovaFaqAnalyticsService;
use App\Services\Dova\DovaSpeechService;
use App\Services\Dova\DovaVoiceAnalyticsService;
use App\Support\Dova\DovaContextResolver;
use App\Support\Dova\DovaCopilotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DovaCopilotController extends Controller
{
    public function __construct(
        protected DovaCopilotService $copilot,
    ) {}

    public function suggest(Request $request): JsonResponse
    {
        if (! $this->copilot->isEnabled()) {
            return response()->json(['enabled' => false], 404);
        }

        $data = $request->validate([
            'message' => 'nullable|string|max:2000',
            'path' => 'nullable|string|max:500',
            'input_method' => 'nullable|string|in:text,voice',
            'detected_language' => 'nullable|string|max:8',
        ]);

        return response()->json(
            $this->copilot->respondToMessage($data['message'] ?? '', $request, $data['path'] ?? null)
        );
    }

    public function context(Request $request): JsonResponse
    {
        $data = $request->validate([
            'path' => 'nullable|string|max:500',
        ]);

        return response()->json($this->copilot->forRequest($request, $data['path'] ?? null));
    }

    public function feedback(Request $request, DovaFaqAnalyticsService $analytics, DovaContextResolver $context): JsonResponse
    {
        $data = $request->validate([
            'helpful' => ['required', 'boolean'],
            'query_id' => ['nullable', 'integer', 'exists:dova_knowledge_queries,id'],
            'faq_id' => ['nullable', 'integer', 'exists:dova_faqs,id'],
            'question' => ['nullable', 'string', 'max:500'],
            'path' => ['nullable', 'string', 'max:500'],
        ]);

        $ctx = $context->resolve($request, $data['path'] ?? null);

        $analytics->recordFeedback(
            helpful: (bool) $data['helpful'],
            queryId: $data['query_id'] ?? null,
            faqId: $data['faq_id'] ?? null,
            question: $data['question'] ?? null,
            portal: (string) ($ctx['portal'] ?? 'public'),
            role: (string) ($ctx['role'] ?? 'guest'),
            userId: $request->user()?->id,
        );

        return response()->json(['ok' => true]);
    }

    public function transcribe(
        Request $request,
        DovaSpeechService $speech,
        DovaVoiceAnalyticsService $voiceAnalytics,
        DovaContextResolver $context,
    ): JsonResponse {
        if (! $this->copilot->isEnabled()) {
            return response()->json(['enabled' => false], 404);
        }

        $data = $request->validate([
            'audio' => ['required', 'file', 'mimetypes:audio/webm,audio/wav,audio/mpeg,audio/mp4,audio/ogg,audio/x-m4a,video/webm', 'max:10240'],
            'path' => 'nullable|string|max:500',
            'hint_language' => 'nullable|string|max:8',
            'duration_ms' => 'nullable|integer|min:0|max:300000',
        ]);

        $ctx = $context->resolve($request, $data['path'] ?? null);
        $started = microtime(true);

        $result = $speech->transcribe(
            $data['audio'],
            $speech->normalizeLanguage($data['hint_language'] ?? null),
        );

        $durationMs = $data['duration_ms'] ?? (int) round((microtime(true) - $started) * 1000);

        $voiceAnalytics->logRecognition(
            success: $result['success'],
            engine: 'whisper',
            context: $ctx,
            transcript: $result['transcript'],
            detectedLanguage: $result['detected_language'],
            errorCode: $result['error'],
            durationMs: $durationMs,
            userId: $request->user()?->id,
        );

        return response()->json([
            'success' => $result['success'],
            'transcript' => $result['transcript'],
            'detected_language' => $result['detected_language'],
            'error' => $result['error'],
        ]);
    }

    public function logRecognition(
        Request $request,
        DovaVoiceAnalyticsService $voiceAnalytics,
        DovaContextResolver $context,
    ): JsonResponse {
        if (! $this->copilot->isEnabled()) {
            return response()->json(['enabled' => false], 404);
        }

        $data = $request->validate([
            'success' => ['required', 'boolean'],
            'engine' => ['required', 'string', 'in:web_speech,whisper'],
            'transcript' => 'nullable|string|max:2000',
            'detected_language' => 'nullable|string|max:8',
            'error_code' => 'nullable|string|max:64',
            'duration_ms' => 'nullable|integer|min:0|max:300000',
            'path' => 'nullable|string|max:500',
        ]);

        $ctx = $context->resolve($request, $data['path'] ?? null);

        $voiceAnalytics->logRecognition(
            success: (bool) $data['success'],
            engine: $data['engine'],
            context: $ctx,
            transcript: $data['transcript'] ?? null,
            detectedLanguage: $data['detected_language'] ?? null,
            errorCode: $data['error_code'] ?? null,
            durationMs: $data['duration_ms'] ?? null,
            userId: $request->user()?->id,
        );

        return response()->json(['ok' => true]);
    }
}
