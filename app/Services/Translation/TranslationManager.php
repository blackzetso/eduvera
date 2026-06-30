<?php

namespace App\Services\Translation;

use App\Services\Translation\Contracts\TranslationServiceInterface;
use InvalidArgumentException;

class TranslationManager
{
    public function driver(?string $name = null): TranslationServiceInterface
    {
        $name = $name ?? config('translation.default', 'openai');

        return match ($name) {
            'openai' => app(OpenAITranslationService::class),
            'deepl' => app(DeepLTranslationService::class),
            'google' => app(GoogleTranslationService::class),
            'null' => app(NullTranslationService::class),
            default => throw new InvalidArgumentException("Unsupported translation provider [{$name}]."),
        };
    }

    public function active(): TranslationServiceInterface
    {
        $driver = $this->driver();

        return $driver->isConfigured() ? $driver : app(NullTranslationService::class);
    }
}
