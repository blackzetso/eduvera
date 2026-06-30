<?php

namespace App\Services\Translation;

use App\Services\Translation\Contracts\TranslationServiceInterface;
use Illuminate\Support\Facades\Log;

class NullTranslationService implements TranslationServiceInterface
{
    public function translateBatch(array $segments, string $from, string $to): array
    {
        Log::warning('Translation skipped: no translation provider configured.', [
            'from' => $from,
            'to' => $to,
            'segments' => array_keys($segments),
        ]);

        return array_fill_keys(array_keys($segments), null);
    }

    public function isConfigured(): bool
    {
        return false;
    }
}
