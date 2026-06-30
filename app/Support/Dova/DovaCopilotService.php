<?php

namespace App\Support\Dova;

use App\Services\Dova\DovaKnowledgeQueryLogger;
use App\Services\Dova\DovaLLMService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class DovaCopilotService
{
    public function __construct(
        protected DovaContextResolver $context,
        protected DovaPersonalityService $personality,
        protected DovaConversationIntentService $conversation,
        protected DovaKnowledgeService $knowledge,
        protected DovaKnowledgeQueryLogger $queryLogger,
        protected DovaLLMService $llm,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('dova.enabled', true);
    }

    public function forRequest(Request $request, ?string $clientPath = null): array
    {
        if (! $this->isEnabled()) {
            return ['enabled' => false];
        }

        $ctx = $this->context->resolve($request, $clientPath);
        $locale = app()->getLocale();
        $welcome = $this->personality->welcomeCard($ctx, $locale);

        return [
            'enabled' => true,
            'locale' => $locale,
            'tagline' => $locale === 'ar' ? 'دليلك الذكي للمدرسة' : 'Your Smart School Guide',
            'context' => $ctx,
            'portal' => $ctx['portal'],
            'role' => $ctx['role'],
            'greeting' => $this->personality->greeting($ctx, $locale),
            'welcome' => $welcome,
            'statusLabel' => $welcome['status'],
            'sampleQuestions' => $this->personality->sampleQuestions($ctx, $locale),
            'quickActions' => $this->quickActions($ctx, $locale),
            'knowledgeDebug' => $this->knowledgeDebugEnabled(),
            'voice' => [
                'whisperFallback' => app(\App\Services\Dova\DovaSpeechService::class)->isWhisperAvailable(),
            ],
        ];
    }

    public function knowledgeDebugEnabled(): bool
    {
        return (bool) config('dova.knowledge_debug', config('dova.demo_mode', false));
    }

    /**
     * @return array{
     *   introduction: string,
     *   explanation: string,
     *   footer: string,
     *   text: string,
     *   expression: string,
     *   actions: array<int, array<string, mixed>>,
     *   workflow: array<int, string>|null,
     *   context: array
     * }
     */
    public function respondToMessage(string $message, Request $request, ?string $clientPath = null): array
    {
        $ctx = $this->context->resolve($request, $clientPath);
        $locale = app()->getLocale();

        $intent = $this->conversation->detect($message);

        if ($intent !== 'workflow') {
            $conversational = $this->conversation->respond($intent, $message, $ctx, $locale);
            $enhanced = $this->llm->rewrite(
                type: 'conversational',
                question: $message,
                locale: $locale,
                ctx: $ctx,
                template: [
                    'introduction' => $conversational['introduction'],
                    'explanation' => $conversational['explanation'],
                    'footer' => $conversational['footer'] ?? '',
                ],
                meta: ['intent' => $intent],
                userId: $request->user()?->id,
            );

            $payload = [
                ...$conversational,
                'introduction' => $enhanced['introduction'],
                'explanation' => $enhanced['explanation'],
                'footer' => $enhanced['footer'],
                'text' => $enhanced['text'],
                'context' => $ctx,
            ];

            if (isset($enhanced['aiDebug'])) {
                $payload['aiDebug'] = $enhanced['aiDebug'];
            }

            return $payload;
        }

        $started = microtime(true);
        $knowledge = $this->knowledge->answer($message, $locale);
        $responseMs = (int) round((microtime(true) - $started) * 1000);

        if ($knowledge['matched']) {
            $queryLog = $this->logKnowledgeQuery($message, $ctx, $knowledge, true, $responseMs, $request);

            return $this->buildKnowledgeResponse($knowledge, $message, $ctx, $locale, $queryLog, $request->user()?->id);
        }

        $this->logKnowledgeQuery($message, $ctx, $knowledge, false, $responseMs, $request);

        $actions = $this->suggestActions($message, $ctx, $locale);
        $voice = $this->personality->buildResponse($message, $actions, $ctx, $locale);

        $noKnowledgeIntro = $this->llm->noKnowledgeMessage($locale);
        $template = [
            'introduction' => $noKnowledgeIntro,
            'explanation' => $voice['explanation'],
            'footer' => $voice['footer'],
        ];

        $enhanced = $this->llm->rewrite(
            type: 'workflow',
            question: $message,
            locale: $locale,
            ctx: $ctx,
            template: $template,
            meta: ['actions' => array_slice($actions, 0, 4)],
            userId: $request->user()?->id,
        );

        $payload = [
            'intent' => 'workflow',
            'introduction' => $enhanced['introduction'],
            'explanation' => $enhanced['explanation'],
            'footer' => $enhanced['footer'],
            'text' => $enhanced['text'],
            'expression' => $voice['expression'],
            'actions' => $actions,
            'workflow' => $voice['workflow'],
            'context' => $ctx,
            'noKnowledge' => true,
        ];

        if (isset($enhanced['aiDebug'])) {
            $payload['aiDebug'] = $enhanced['aiDebug'];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $knowledge
     * @param  array<string, mixed>  $ctx
     */
    protected function logKnowledgeQuery(
        string $message,
        array $ctx,
        array $knowledge,
        bool $answered,
        int $responseMs,
        Request $request,
    ): ?\App\Models\DovaKnowledgeQuery {
        try {
            $preview = implode(' ', array_filter([
                $knowledge['introduction'] ?? '',
                $knowledge['explanation'] ?? '',
            ]));

            $inputMeta = $this->inputMetaFromRequest($request);

            return $this->queryLogger->log(
                question: $message,
                context: $ctx,
                answered: $answered,
                intent: $answered ? 'knowledge' : null,
                sourceSlug: $knowledge['source'] ?? null,
                recordKey: $knowledge['record'] ?? null,
                confidence: $knowledge['confidence'] ?? null,
                responseMs: $responseMs,
                matchedContent: $knowledge['matchedText'] ?? null,
                answerPreview: $preview !== '' ? mb_substr($preview, 0, 500) : null,
                userId: $request->user()?->id,
                inputMethod: $inputMeta['input_method'],
                detectedLanguage: $inputMeta['detected_language'],
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $knowledge
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    protected function buildKnowledgeResponse(
        array $knowledge,
        string $message,
        array $ctx,
        string $locale,
        ?\App\Models\DovaKnowledgeQuery $queryLog = null,
        ?int $userId = null,
    ): array {
        $actions = array_slice($this->suggestActions($message, $ctx, $locale), 0, 2);

        $enhanced = $this->llm->enhanceKnowledge($message, $knowledge, $locale, $ctx, $userId);

        $payload = [
            'intent' => 'knowledge',
            'introduction' => $enhanced['introduction'],
            'explanation' => $enhanced['explanation'],
            'footer' => $enhanced['footer'],
            'text' => $enhanced['text'],
            'expression' => $knowledge['expression'] ?? 'explaining',
            'actions' => $actions,
            'workflow' => null,
            'context' => $ctx,
            'queryId' => $queryLog?->id,
            'faqId' => $knowledge['faqId'] ?? null,
            'showFeedback' => true,
            'usedLlm' => $enhanced['used_llm'] ?? false,
            'llmFallback' => $enhanced['fallback'] ?? true,
        ];

        if ($this->knowledgeDebugEnabled()) {
            $payload['knowledgeDebug'] = [
                'source' => $knowledge['source'],
                'record' => $knowledge['record'],
                'confidence' => $knowledge['confidence'],
                'matchedText' => $knowledge['matchedText'] ?? null,
                'rawIntroduction' => $knowledge['introduction'],
                'rawExplanation' => $knowledge['explanation'],
                'rawFooter' => $knowledge['footer'],
            ];
        }

        if (isset($enhanced['aiDebug'])) {
            $payload['aiDebug'] = $enhanced['aiDebug'];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<int, array<string, mixed>>
     */
    public function quickActions(array $ctx, string $locale): array
    {
        return $this->rankActions(
            collect(DovaActionCatalog::all()),
            '',
            $ctx,
            $locale,
            (int) config('dova.max_quick_actions', 6),
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<int, array<string, mixed>>
     */
    public function suggestActions(string $message, array $ctx, string $locale): array
    {
        $limit = (int) config('dova.max_suggested_actions', 4);

        return $this->rankActions(
            collect(DovaActionCatalog::all()),
            $message,
            $ctx,
            $locale,
            $limit,
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<string, array<string, mixed>>  $catalog
     * @param  array<string, mixed>  $ctx
     * @return array<int, array<string, mixed>>
     */
    protected function rankActions($catalog, string $message, array $ctx, string $locale, int $limit): array
    {
        $needle = mb_strtolower(trim($message));
        $portal = $ctx['portal'];
        $role = $ctx['role'];
        $pageContext = $ctx['page_context'];

        $scored = [];

        foreach ($catalog as $id => $def) {
            if (! $this->actionAllowed($def, $portal, $role)) {
                continue;
            }

            $score = (int) ($def['priority'] ?? 0);

            if ($this->contextMatches($def, $pageContext)) {
                $score += 30;
            }

            if ($needle !== '') {
                foreach ($def['keywords'] ?? [] as $keyword) {
                    if (mb_stripos($needle, mb_strtolower($keyword)) !== false) {
                        $score += 20;
                    }
                }
            }

            if ($needle === '' || $score > (int) ($def['priority'] ?? 0)) {
                $scored[$id] = $score;
            }
        }

        arsort($scored);

        $actions = [];

        foreach (array_keys($scored) as $id) {
            $resolved = $this->resolveAction($id, $catalog[$id], $locale, $ctx);

            if ($resolved !== null) {
                $actions[] = $resolved;
            }

            if (count($actions) >= $limit) {
                break;
            }
        }

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $def
     */
    protected function actionAllowed(array $def, string $portal, string $role): bool
    {
        if (! in_array($portal, $def['portals'] ?? [], true)) {
            return false;
        }

        $roles = $def['roles'] ?? [];

        if ($roles === []) {
            return true;
        }

        if ($role === 'guest') {
            return in_array('guest', $roles, true);
        }

        return in_array($role, $roles, true);
    }

    /**
     * @param  array<string, mixed>  $def
     */
    protected function contextMatches(array $def, string $pageContext): bool
    {
        $contexts = $def['contexts'] ?? [];

        return $contexts !== [] && in_array($pageContext, $contexts, true);
    }

    /**
     * @param  array<string, mixed>  $def
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>|null
     */
    protected function resolveAction(string $id, array $def, string $locale, array $ctx): ?array
    {
        $href = $this->resolveHref($def, $ctx);

        if ($href === null) {
            return null;
        }

        return [
            'id' => $id,
            'label' => $def['label'][$locale] ?? $def['label']['en'] ?? $id,
            'type' => $def['type'],
            'href' => $href,
            'icon' => $def['icon'] ?? 'bi-lightning',
            'expression' => $def['expression'] ?? 'helping',
            'external' => (bool) ($def['external'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $def
     * @param  array<string, mixed>  $ctx
     */
    protected function resolveHref(array $def, array $ctx): ?string
    {
        $params = $def['route_params'] ?? [];

        if (isset($def['route_params_from_context'])) {
            foreach ($def['route_params_from_context'] as $param => $key) {
                if (isset($ctx[$key])) {
                    $params[$param] = $ctx[$key];
                }
            }
        }

        return match ($def['type'] ?? 'route') {
            'route' => $this->safeRoute($def['route'] ?? null, $params),
            'anchor' => $def['anchor'] ?? null,
            'url' => $def['url'] ?? null,
            default => null,
        };
    }

    protected function safeRoute(?string $name, array $params = []): ?string
    {
        if ($name === null || ! Route::has($name)) {
            return null;
        }

        try {
            return route($name, $params);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{input_method: string, detected_language: ?string}
     */
    protected function inputMetaFromRequest(Request $request): array
    {
        $method = (string) $request->input('input_method', 'text');

        return [
            'input_method' => in_array($method, ['text', 'voice'], true) ? $method : 'text',
            'detected_language' => filled($request->input('detected_language'))
                ? mb_substr((string) $request->input('detected_language'), 0, 8)
                : null,
        ];
    }
}
