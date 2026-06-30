<?php

namespace App\Services\Translation;

use App\Services\Translation\Contracts\TranslationServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAITranslationService implements TranslationServiceInterface
{
    public function translateBatch(array $segments, string $from, string $to): array
    {
        $segments = array_filter($segments, fn ($text) => is_string($text) && trim($text) !== '');

        if ($segments === []) {
            return [];
        }

        if (! $this->isConfigured()) {
            return app(NullTranslationService::class)->translateBatch($segments, $from, $to);
        }

        $config = config('translation.providers.openai');
        $localeNames = ['en' => 'English', 'ar' => 'Arabic'];

        $payload = json_encode([
            'from' => $localeNames[$from] ?? $from,
            'to' => $localeNames[$to] ?? $to,
            'segments' => $segments,
        ], JSON_UNESCAPED_UNICODE);

        try {
            $response = Http::timeout((int) config('translation.timeout_seconds', 30))
                ->withToken($config['api_key'])
                ->acceptJson()
                ->post(rtrim($config['base_url'], '/').'/chat/completions', [
                    'model' => $config['model'],
                    'temperature' => $config['temperature'],
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => config('translation.system_prompt')],
                        [
                            'role' => 'user',
                            'content' => "Translate each segment in this JSON object. Return JSON with the same keys and translated string values only:\n".$payload,
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::error('OpenAI translation request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return array_fill_keys(array_keys($segments), null);
            }

            $content = data_get($response->json(), 'choices.0.message.content');
            $decoded = json_decode((string) $content, true);

            if (! is_array($decoded)) {
                Log::error('OpenAI translation returned invalid JSON', ['content' => $content]);

                return array_fill_keys(array_keys($segments), null);
            }

            $translations = is_array($decoded['segments'] ?? null) ? $decoded['segments'] : $decoded;
            $out = [];

            foreach (array_keys($segments) as $key) {
                $value = $translations[$key] ?? null;
                $out[$key] = is_string($value) && trim($value) !== '' ? trim($value) : null;
            }

            return $out;
        } catch (\Throwable $e) {
            Log::error('OpenAI translation exception', ['message' => $e->getMessage()]);

            return array_fill_keys(array_keys($segments), null);
        }
    }

    public function isConfigured(): bool
    {
        return filled(config('translation.providers.openai.api_key'));
    }
}
