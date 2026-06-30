<?php

namespace App\Support\Admission;

class AdmissionEngagementChannel
{
    public const WEBSITE = 'website';

    public const PHONE = 'phone';

    public const WHATSAPP = 'whatsapp';

    public const EMAIL = 'email';

    public const VISIT = 'visit';

    public const INTERNAL = 'internal';

    public static function label(string $channel): string
    {
        $locale = str_starts_with(app()->getLocale(), 'ar') ? 'label_ar' : 'label_en';

        return config("admission_engagements.channels.{$channel}.{$locale}")
            ?? config("admission_engagements.channels.{$channel}.label_en")
            ?? $channel;
    }
}
