<?php

namespace App\Services\Dova;

use App\Models\DovaAiUsageLog;
use App\Support\Dova\DovaPersonalityService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DovaLLMService
{
    public function __construct(
        protected DovaPersonalityService $personality,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('dova-ai.enabled', false)
            && filled(config('dova-ai.api_key'));
    }

    public function debugEnabled(): bool
    {
        return (bool) config('dova-ai.debug', false);
    }

    /**
     * Rewrite a template response using OpenAI. Falls back to template on failure.
     *
     * @param  array{introduction: string, explanation: string, footer: string}  $template
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function rewrite(
        string $type,
        string $question,
        string $locale,
        array $ctx,
        array $template,
        array $meta = [],
        ?int $userId = null,
    ): array {
        $fallback = $this->buildOutput($template, false, true);

        if (! $this->isEnabled()) {
            return $fallback;
        }

        $started = microtime(true);
        $userPrompt = $this->buildUserPrompt($type, $question, $locale, $ctx, $template, $meta);

        try {
            $response = Http::timeout((int) config('dova-ai.timeout_seconds', 25))
                ->withToken(config('dova-ai.api_key'))
                ->acceptJson()
                ->post(rtrim(config('dova-ai.base_url'), '/').'/chat/completions', [
                    'model' => config('dova-ai.model'),
                    'temperature' => (float) config('dova-ai.temperature', 0.3),
                    'max_tokens' => (int) config('dova-ai.max_tokens', 1200),
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt($ctx, $locale, $type)],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ]);

            $responseMs = (int) round((microtime(true) - $started) * 1000);

            if (! $response->successful()) {
                Log::warning('Dova LLM request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                $this->logUsage($type, $question, $ctx, $userId, $responseMs, [], false, true);

                return $fallback;
            }

            $json = $response->json();
            $content = (string) data_get($json, 'choices.0.message.content', '');
            $parsed = json_decode($content, true);

            if (! is_array($parsed)) {
                $this->logUsage($type, $question, $ctx, $userId, $responseMs, $json, false, true);

                return $fallback;
            }

            $usage = [
                'model' => (string) ($json['model'] ?? config('dova-ai.model')),
                'prompt_tokens' => (int) data_get($json, 'usage.prompt_tokens', 0),
                'completion_tokens' => (int) data_get($json, 'usage.completion_tokens', 0),
                'total_tokens' => (int) data_get($json, 'usage.total_tokens', 0),
            ];
            $usage['estimated_cost'] = $this->estimateCost(
                $usage['model'],
                $usage['prompt_tokens'],
                $usage['completion_tokens'],
            );

            $this->logUsage($type, $question, $ctx, $userId, $responseMs, $usage, true, false);

            $output = $this->buildOutput([
                'introduction' => $this->sanitizeSegment($parsed['introduction'] ?? $template['introduction']),
                'explanation' => $this->sanitizeSegment($parsed['explanation'] ?? $template['explanation']),
                'footer' => $this->sanitizeSegment($parsed['footer'] ?? $template['footer']),
            ], true, false);

            if ($this->debugEnabled()) {
                $output['aiDebug'] = [
                    'requestType' => $type,
                    'systemPrompt' => $this->systemPrompt($ctx, $locale, $type),
                    'userPrompt' => $userPrompt,
                    'rawResponse' => $content,
                    'model' => $usage['model'],
                    'promptTokens' => $usage['prompt_tokens'],
                    'completionTokens' => $usage['completion_tokens'],
                    'totalTokens' => $usage['total_tokens'],
                    'estimatedCost' => $usage['estimated_cost'],
                    'responseMs' => $responseMs,
                ];
            }

            return $output;
        } catch (\Throwable $e) {
            Log::warning('Dova LLM exception', ['message' => $e->getMessage()]);
            $responseMs = (int) round((microtime(true) - $started) * 1000);
            $this->logUsage($type, $question, $ctx, $userId, $responseMs, [], false, true);

            return $fallback;
        }
    }

    /**
     * @param  array{introduction: string, explanation: string, footer: string}  $knowledge
     * @param  array<string, mixed>  $ctx
     */
    public function enhanceKnowledge(
        string $question,
        array $knowledge,
        string $locale,
        array $ctx,
        ?int $userId = null,
    ): array {
        return $this->rewrite(
            type: 'knowledge',
            question: $question,
            locale: $locale,
            ctx: $ctx,
            template: [
                'introduction' => $knowledge['introduction'] ?? '',
                'explanation' => $knowledge['explanation'] ?? '',
                'footer' => $knowledge['footer'] ?? '',
            ],
            meta: [
                'source' => $knowledge['source'] ?? null,
                'record' => $knowledge['record'] ?? null,
                'confidence' => $knowledge['confidence'] ?? null,
                'matchedText' => $knowledge['matchedText'] ?? null,
            ],
            userId: $userId,
        );
    }

    public function noKnowledgeMessage(string $locale): string
    {
        $messages = config('dova-ai.no_knowledge_message', []);

        return $messages[$locale] ?? $messages['en'] ?? "I'm sorry, I couldn't find information about that yet.";
    }

    /**
     * @param  array{introduction: string, explanation: string, footer: string}  $template
     * @return array<string, mixed>
     */
    protected function buildOutput(array $template, bool $usedLlm, bool $fallback): array
    {
        $introduction = trim($template['introduction'] ?? '');
        $explanation = trim($template['explanation'] ?? '');
        $footer = trim($template['footer'] ?? '');

        return [
            'used_llm' => $usedLlm,
            'fallback' => $fallback,
            'introduction' => $introduction,
            'explanation' => $explanation,
            'footer' => $footer,
            'text' => implode("\n\n", array_filter([$introduction, $explanation, $footer])),
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    protected function systemPrompt(array $ctx, string $locale, string $type): string
    {
        $base = trim(config('dova-ai.system_prompt', ''));
        $lang = $locale === 'ar' ? 'Arabic' : 'English';
        $tone = $this->personality->toneProfile($ctx);

        return implode("\n\n", array_filter([
            $base,
            "Response language: {$lang}.",
            "User portal: ".($ctx['portal'] ?? 'public').'.',
            "User role: ".($ctx['role'] ?? 'guest').'.',
            "Tone: {$tone}.",
            "Request type: {$type}.",
        ]));
    }

    /**
     * @param  array{introduction: string, explanation: string, footer: string}  $template
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $meta
     */
    protected function buildUserPrompt(
        string $type,
        string $question,
        string $locale,
        array $ctx,
        array $template,
        array $meta,
    ): string {
        $lang = $locale === 'ar' ? 'Arabic' : 'English';
        $parts = [
            "Question: {$question}",
            "Language: {$lang}",
            '',
        ];

        if ($type === 'knowledge') {
            $parts[] = 'Retrieved Knowledge (use ONLY this — do not add facts):';
            if (! empty($meta['source'])) {
                $parts[] = 'Source: '.$meta['source'];
            }
            if (! empty($meta['record'])) {
                $parts[] = 'Record: '.$meta['record'];
            }
            if (isset($meta['confidence'])) {
                $parts[] = 'Confidence: '.$meta['confidence'];
            }
            if (! empty($meta['matchedText'])) {
                $parts[] = 'Matched content: '.$meta['matchedText'];
            }
            $parts[] = '';
        }

        if ($type === 'conversational' && ! empty($meta['intent'])) {
            $parts[] = 'Conversational intent: '.$meta['intent'];
            $parts[] = '';
        }

        if ($type === 'workflow' && ! empty($meta['actions'])) {
            $parts[] = 'Available navigation actions (do not invent new ones):';
            foreach ($meta['actions'] as $action) {
                $parts[] = '- '.($action['label'] ?? $action['id'] ?? 'action');
            }
            $parts[] = '';
        }

        $parts[] = 'Template response to improve:';
        $parts[] = 'Introduction: '.$template['introduction'];
        $parts[] = 'Explanation: '.$template['explanation'];
        $parts[] = 'Footer: '.$template['footer'];
        $parts[] = '';
        $parts[] = match ($type) {
            'knowledge' => 'Task: Create a natural, warm answer using ONLY the retrieved knowledge above. Improve wording and flow. Do not add any facts not present in the knowledge.',
            'conversational' => 'Task: Make this conversational response sound natural and warm. Do not add school-specific facts.',
            default => 'Task: Improve this guidance response naturally. Use only the template content and listed actions. Do not invent school facts.',
        };

        return implode("\n", $parts);
    }

    protected function sanitizeSegment(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    protected function estimateCost(string $model, int $promptTokens, int $completionTokens): float
    {
        $pricing = config("dova-ai.pricing.{$model}")
            ?? config('dova-ai.pricing.gpt-4o-mini', ['input' => 0.15, 'output' => 0.60]);

        $inputCost = ($promptTokens / 1_000_000) * ($pricing['input'] ?? 0);
        $outputCost = ($completionTokens / 1_000_000) * ($pricing['output'] ?? 0);

        return round($inputCost + $outputCost, 6);
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $usage
     */
    protected function logUsage(
        string $type,
        string $question,
        array $ctx,
        ?int $userId,
        int $responseMs,
        array $usage,
        bool $success,
        bool $usedFallback,
    ): void {
        try {
            DovaAiUsageLog::query()->create([
                'model' => $usage['model'] ?? config('dova-ai.model'),
                'request_type' => $type,
                'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
                'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
                'estimated_cost' => (float) ($usage['estimated_cost'] ?? 0),
                'response_ms' => $responseMs,
                'portal' => (string) ($ctx['portal'] ?? 'public'),
                'role' => (string) ($ctx['role'] ?? 'guest'),
                'user_id' => $userId,
                'question' => mb_substr($question, 0, 500),
                'success' => $success,
                'used_fallback' => $usedFallback,
            ]);
        } catch (\Throwable) {
            // Usage logging must not break responses.
        }
    }
}
