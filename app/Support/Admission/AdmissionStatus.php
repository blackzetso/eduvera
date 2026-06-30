<?php

namespace App\Support\Admission;

class AdmissionStatus
{
    public const OPEN = 'open';

    public const CONVERTED = 'converted';

    public const REJECTED = 'rejected';

    public const WITHDRAWN = 'withdrawn';

    public const WAITLISTED = 'waitlisted';

    public static function all(): array
    {
        return [
            self::OPEN,
            self::CONVERTED,
            self::REJECTED,
            self::WITHDRAWN,
            self::WAITLISTED,
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

    public static function label(string $status): string
    {
        $locale = app()->getLocale();
        $key = str_starts_with($locale, 'ar') ? 'label_ar' : 'label_en';

        return config("admissions.statuses.{$status}.{$key}")
            ?? config("admissions.statuses.{$status}.label_en")
            ?? $status;
    }
}
