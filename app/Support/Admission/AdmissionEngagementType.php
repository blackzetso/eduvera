<?php

namespace App\Support\Admission;

class AdmissionEngagementType
{
    public const WEBSITE_FORM = 'website_form';

    public const PHONE_CALL = 'phone_call';

    public const WHATSAPP = 'whatsapp';

    public const EMAIL = 'email';

    public const FOLLOW_UP = 'follow_up';

    public const CAMPUS_VISIT = 'campus_visit';

    public const MEETING = 'meeting';

    public const NOTE = 'note';

    public const TASK = 'task';

    public static function all(): array
    {
        return [
            self::WEBSITE_FORM,
            self::PHONE_CALL,
            self::WHATSAPP,
            self::EMAIL,
            self::FOLLOW_UP,
            self::CAMPUS_VISIT,
            self::MEETING,
            self::NOTE,
            self::TASK,
        ];
    }

    public static function label(string $type): string
    {
        $locale = str_starts_with(app()->getLocale(), 'ar') ? 'label_ar' : 'label_en';

        return config("admission_engagements.types.{$type}.{$locale}")
            ?? config("admission_engagements.types.{$type}.label_en")
            ?? $type;
    }

    public static function icon(string $type): string
    {
        return config("admission_engagements.types.{$type}.icon") ?? 'bi-circle';
    }
}
