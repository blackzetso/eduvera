<?php

use App\Models\Language;
use App\Models\LanguagePhrase;



if (! function_exists('get_phrase')) {
    function get_phrase(string $key, string $group = 'general'): string
    {
        $locale = app()->getLocale();

        // هات اللغة الحالية أو الافتراضية من الكاش
        $language = cache()->rememberForever("lang_{$locale}", function () use ($locale) {
            return Language::where('code', $locale)
                ->where('status', 'enabled')
                ->first()
                ??Language::where('is_default', 1)->first();
        });

        if (! $language) {
            return $key; // fallback لو مفيش لغة أصلاً
        }

        // هات الكلمة من الكاش
        $phrase = cache()->rememberForever("phrase_{$language->id}_{$group}_{$key}", function () use ($language, $group, $key) {
            return LanguagePhrase::where('language_id', $language->id)
                ->where('group', $group)
                ->where('key', $key)
                ->first();
        });

        if ($phrase && $phrase->word) {
            return $phrase->word;
        }

        // fallback للغة الافتراضية
        $defaultLang = cache()->rememberForever("lang_default", function () {
            return Language::where('is_default', 1)->first();
        });

        if ($defaultLang && $defaultLang->id !== $language->id) {
            $fallback = cache()->rememberForever("phrase_{$defaultLang->id}_{$group}_{$key}", function () use ($defaultLang, $group, $key) {
                return LanguagePhrase::where('language_id', $defaultLang->id)
                    ->where('group', $group)
                    ->where('key', $key)
                    ->first();
            });

            if ($fallback && $fallback->word) {
                return $fallback->word;
            }
        }

        return $key; // fallback نهائي لو مش لاقي الترجمة
    }
}

