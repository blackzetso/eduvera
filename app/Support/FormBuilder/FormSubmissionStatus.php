<?php

namespace App\Support\FormBuilder;

class FormSubmissionStatus
{
    public const DRAFT = 'draft';

    public const SUBMITTED = 'submitted';

    public const UNDER_REVIEW = 'under_review';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return config('form-builder.submission_statuses', [
            self::DRAFT,
            self::SUBMITTED,
            self::UNDER_REVIEW,
            self::APPROVED,
            self::REJECTED,
        ]);
    }

    public static function isValid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }

    public static function canTransition(string $from, string $to): bool
    {
        $allowed = config('form-builder.submission_transitions', []);

        return in_array($to, $allowed[$from] ?? [], true);
    }

    /**
     * @return array<int, string>
     */
    public static function allowedTransitions(string $from): array
    {
        return config('form-builder.submission_transitions', [])[$from] ?? [];
    }
}
