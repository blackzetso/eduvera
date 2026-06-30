<?php

namespace App\Services\Translation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class BilingualAutoTranslationService
{
    public function __construct(
        protected TranslationManager $manager,
        protected BilingualFieldResolver $resolver,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('translation.enabled', true);
    }

    /**
     * Translate missing bilingual fields in a flat or nested array payload.
     */
    public function translatePayload(array $payload): array
    {
        if (! $this->isEnabled()) {
            return $payload;
        }

        try {
            return $this->translateNode($payload);
        } catch (\Throwable $e) {
            Log::error('Bilingual auto-translation failed for payload', [
                'message' => $e->getMessage(),
            ]);

            return $payload;
        }
    }

    /**
     * Apply auto-translation to an Eloquent model before save.
     */
    public function translateModel(Model $model): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        try {
            $attributes = $model->getAttributes();
            $translated = $this->translatePayload($attributes);

            foreach ($translated as $key => $value) {
                if (! array_key_exists($key, $attributes)) {
                    continue;
                }

                if ($translated[$key] !== $attributes[$key]) {
                    $model->setAttribute($key, $value);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Bilingual auto-translation failed for model', [
                'model' => $model::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function estimateCharacterCount(array $payload): int
    {
        return $this->sumStringLengths($payload);
    }

    public function shouldUseQueue(array $payload): bool
    {
        return $this->estimateCharacterCount($payload) > (int) config('translation.sync_max_characters', 4000);
    }

    protected function translateNode(array $node): array
    {
        $node = $this->translateFlatLevel($node);

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                if ($this->isListArray($value)) {
                    $node[$key] = array_map(function ($item) {
                        return is_array($item) ? $this->translateNode($item) : $item;
                    }, $value);
                } else {
                    $node[$key] = $this->translateNode($value);
                }
            }
        }

        return $node;
    }

    protected function translateFlatLevel(array $node): array
    {
        $pairs = $this->resolver->pairsFromFlatArray($node);

        if ($pairs === []) {
            return $node;
        }

        $enToAr = [];
        $arToEn = [];

        foreach ($pairs as $pair) {
            if ($pair['direction'] === 'en_to_ar') {
                $enToAr[$pair['base']] = trim((string) $node[$pair['base']]);
            } else {
                $arToEn[$pair['base']] = trim((string) $node[$pair['arabic']]);
            }
        }

        $translator = $this->manager->active();

        if ($enToAr !== []) {
            $translations = $translator->translateBatch($enToAr, 'en', 'ar');
            foreach ($pairs as $pair) {
                if ($pair['direction'] !== 'en_to_ar') {
                    continue;
                }
                $translated = $translations[$pair['base']] ?? null;
                if ($translated !== null && $this->isEmpty($node[$pair['arabic']] ?? null)) {
                    $node[$pair['arabic']] = $translated;
                }
            }
        }

        if ($arToEn !== []) {
            $translations = $translator->translateBatch($arToEn, 'ar', 'en');
            foreach ($pairs as $pair) {
                if ($pair['direction'] !== 'ar_to_en') {
                    continue;
                }
                $translated = $translations[$pair['base']] ?? null;
                if ($translated !== null && $this->isEmpty($node[$pair['base']] ?? null)) {
                    $node[$pair['base']] = $translated;
                }
            }
        }

        return $node;
    }

    protected function isListArray(array $array): bool
    {
        if ($array === []) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    protected function isEmpty(mixed $value): bool
    {
        return ! is_string($value) || trim($value) === '';
    }

    protected function sumStringLengths(mixed $node): int
    {
        if (is_string($node)) {
            return mb_strlen($node);
        }

        if (! is_array($node)) {
            return 0;
        }

        $sum = 0;
        foreach ($node as $value) {
            $sum += $this->sumStringLengths($value);
        }

        return $sum;
    }
}
