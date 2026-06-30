<?php

namespace App\Support\Admission;

class AdmissionDecision
{
    public const ACCEPTED = 'accepted';

    public const REJECTED = 'rejected';

    public const WAITLISTED = 'waitlisted';

    public const WITHDRAWN = 'withdrawn';

    public const CONVERTED = 'converted';

    public static function all(): array
    {
        return [
            self::ACCEPTED,
            self::REJECTED,
            self::WAITLISTED,
            self::WITHDRAWN,
            self::CONVERTED,
        ];
    }

    public static function options(): array
    {
        return collect(self::all())
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => self::label($value),
            ])
            ->values()
            ->all();
    }

    public static function label(?string $decision): string
    {
        if (! $decision) {
            return 'لم يُبتّ';
        }

        $locale = app()->getLocale();
        $key = str_starts_with($locale, 'ar') ? 'label_ar' : 'label_en';

        return config("admissions.decisions.{$decision}.{$key}")
            ?? config("admissions.decisions.{$decision}.label_en")
            ?? $decision;
    }

    public static function isTerminal(string $decision): bool
    {
        return in_array($decision, [
            self::REJECTED,
            self::WITHDRAWN,
            self::CONVERTED,
        ], true);
    }
}
