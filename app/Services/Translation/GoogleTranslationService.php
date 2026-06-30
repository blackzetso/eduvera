<?php

namespace App\Services\Translation;

use App\Services\Translation\Contracts\TranslationServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleTranslationService implements TranslationServiceInterface
{
    public function translateBatch(array $segments, string $from, string $to): array
    {
        if (! $this->isConfigured()) {
            return app(NullTranslationService::class)->translateBatch($segments, $from, $to);
        }

        $config = config('translation.providers.google');
        $out = [];

        foreach ($segments as $key => $text) {
            if (! is_string($text) || trim($text) === '') {
                $out[$key] = null;
                continue;
            }

            try {
                $response = Http::timeout((int) config('translation.timeout_seconds', 30))
                    ->post('https://translation.googleapis.com/language/translate/v2', [
                        'key' => $config['api_key'],
                        'q' => $text,
                        'source' => $from,
                        'target' => $to,
                        'format' => 'html',
                    ]);

                if (! $response->successful()) {
                    Log::error('Google translation failed', ['key' => $key, 'body' => $response->body()]);
                    $out[$key] = null;
                    continue;
                }

                $out[$key] = data_get($response->json(), 'data.translations.0.translatedText');
            } catch (\Throwable $e) {
                Log::error('Google translation exception', ['key' => $key, 'message' => $e->getMessage()]);
                $out[$key] = null;
            }
        }

        return $out;
    }

    public function isConfigured(): bool
    {
        return filled(config('translation.providers.google.api_key'));
    }
}
