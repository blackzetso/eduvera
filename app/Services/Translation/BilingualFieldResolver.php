<?php

namespace App\Services\Translation;

class BilingualFieldResolver
{
    /**
     * Detect translatable pairs at the current array level only.
     *
     * @return array<int, array{base: string, arabic: string, direction: string}>
     */
    public function pairsFromFlatArray(array $data): array
    {
        return array_merge(
            $this->pairsFromArabicSuffix($data),
            $this->pairsFromEnglishSuffix($data),
        );
    }

    /**
     * @return array<int, array{base: string, arabic: string, direction: string}>
     */
    protected function pairsFromArabicSuffix(array $data): array
    {
        $pairs = [];
        $skip = array_flip(config('translation.skip_fields', []));
        $arSuffix = (string) config('translation.suffix.arabic', '_ar');

        foreach ($data as $key => $value) {
            if (! is_string($key) || ! str_ends_with($key, $arSuffix)) {
                continue;
            }

            $stem = substr($key, 0, -strlen($arSuffix));

            if ($stem === '' || isset($skip[$stem]) || isset($skip[$key])) {
                continue;
            }

            $englishKey = match (true) {
                array_key_exists($stem, $data) => $stem,
                array_key_exists($stem.'_en', $data) => $stem.'_en',
                default => null,
            };

            if ($englishKey === null || isset($skip[$englishKey])) {
                continue;
            }

            $pair = $this->buildPair(
                $englishKey,
                $key,
                $data[$englishKey] ?? null,
                $data[$key] ?? null,
            );

            if ($pair !== null) {
                $pairs[] = $pair;
            }
        }

        return $pairs;
    }

    /**
     * Supports legacy pairs like `name` + `name_en` where Arabic omits the `_ar` suffix.
     *
     * @return array<int, array{base: string, arabic: string, direction: string}>
     */
    protected function pairsFromEnglishSuffix(array $data): array
    {
        $pairs = [];
        $skip = array_flip(config('translation.skip_fields', []));
        $enSuffix = (string) config('translation.suffix.english', '_en');
        $arSuffix = (string) config('translation.suffix.arabic', '_ar');

        foreach ($data as $key => $value) {
            if (! is_string($key) || ! str_ends_with($key, $enSuffix)) {
                continue;
            }

            $stem = substr($key, 0, -strlen($enSuffix));

            if ($stem === '' || isset($skip[$stem]) || isset($skip[$key])) {
                continue;
            }

            if (! array_key_exists($stem, $data) || array_key_exists($stem.$arSuffix, $data)) {
                continue;
            }

            $pair = $this->buildPair(
                $key,
                $stem,
                $data[$key] ?? null,
                $data[$stem] ?? null,
            );

            if ($pair !== null) {
                $pairs[] = $pair;
            }
        }

        return $pairs;
    }

    /**
     * @return array{base: string, arabic: string, direction: string}|null
     */
    protected function buildPair(string $base, string $arabicKey, mixed $baseValue, mixed $arabicValue): ?array
    {
        $baseText = $this->normaliseText($baseValue);
        $arabicText = $this->normaliseText($arabicValue);

        if ($baseText !== null && $arabicText === null) {
            return [
                'base' => $base,
                'arabic' => $arabicKey,
                'direction' => 'en_to_ar',
            ];
        }

        if ($arabicText !== null && $baseText === null) {
            return [
                'base' => $base,
                'arabic' => $arabicKey,
                'direction' => 'ar_to_en',
            ];
        }

        return null;
    }

    protected function normaliseText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
