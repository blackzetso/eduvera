<?php

namespace App\Services\Translation;

use App\Services\Translation\Contracts\TranslationServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeepLTranslationService implements TranslationServiceInterface
{
    public function translateBatch(array $segments, string $from, string $to): array
    {
        if (! $this->isConfigured()) {
            return app(NullTranslationService::class)->translateBatch($segments, $from, $to);
        }

        $deeplFrom = strtoupper($from === 'en' ? 'EN' : $from);
        $deeplTo = strtoupper($to === 'ar' ? 'AR' : $to);
        $config = config('translation.providers.deepl');
        $out = [];

        foreach ($segments as $key => $text) {
            if (! is_string($text) || trim($text) === '') {
                $out[$key] = null;
                continue;
            }

            try {
                $response = Http::timeout((int) config('translation.timeout_seconds', 30))
                    ->asForm()
                    ->withHeader('Authorization', 'DeepL-Auth-Key '.$config['api_key'])
                    ->post(rtrim($config['base_url'], '/').'/translate', [
                        'text' => $text,
                        'source_lang' => $deeplFrom,
                        'target_lang' => $deeplTo,
                        'tag_handling' => 'html',
                    ]);

                if (! $response->successful()) {
                    Log::error('DeepL translation failed', ['key' => $key, 'body' => $response->body()]);
                    $out[$key] = null;
                    continue;
                }

                $out[$key] = data_get($response->json(), 'translations.0.text');
            } catch (\Throwable $e) {
                Log::error('DeepL translation exception', ['key' => $key, 'message' => $e->getMessage()]);
                $out[$key] = null;
            }
        }

        return $out;
    }

    public function isConfigured(): bool
    {
        return filled(config('translation.providers.deepl.api_key'));
    }
}
