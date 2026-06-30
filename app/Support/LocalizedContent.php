<?php

namespace App\Support;

final class LocalizedContent
{
    public static function resolve(mixed $value, string $locale, string $fallback = 'en'): mixed
    {
        if (is_array($value)) {
            if (isset($value[$locale]) || isset($value[$fallback])) {
                $hasOnlyLocaleKeys = count(array_diff(array_keys($value), ['ar', 'en'])) === 0;

                if ($hasOnlyLocaleKeys) {
                    return $value[$locale] ?? $value[$fallback] ?? reset($value);
                }
            }

            $resolved = [];

            foreach ($value as $key => $item) {
                if (is_string($key) && (str_ends_with($key, '_ar') || str_ends_with($key, '_en'))) {
                    continue;
                }

                $localizedKey = is_string($key) ? "{$key}_{$locale}" : null;

                if ($localizedKey && array_key_exists($localizedKey, $value) && $value[$localizedKey] !== null && $value[$localizedKey] !== '') {
                    $resolved[$key] = $value[$localizedKey];
                } elseif (is_array($item)) {
                    $resolved[$key] = static::resolve($item, $locale, $fallback);
                } else {
                    $resolved[$key] = $item;
                }
            }

            return $resolved;
        }

        return $value;
    }
}
