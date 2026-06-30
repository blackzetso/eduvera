<?php

namespace App\Support\Admission;

class AdmissionEngagementStatus
{
    public const PENDING = 'pending';

    public const SCHEDULED = 'scheduled';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    public const FAILED = 'failed';

    public static function label(string $status): string
    {
        $locale = str_starts_with(app()->getLocale(), 'ar') ? 'label_ar' : 'label_en';

        return config("admission_engagements.statuses.{$status}.{$locale}")
            ?? config("admission_engagements.statuses.{$status}.label_en")
            ?? $status;
    }
}
