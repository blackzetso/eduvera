<?php

namespace App\Support\Admission;

class AdmissionDocumentStatus
{
    public const NEEDS_UPLOAD = 'needs_upload';

    public const REVIEW_PENDING = 'review_pending';

    public const APPROVED = 'approved';

    public const REUPLOAD_REQUIRED = 'reupload_required';

    public const REJECTED = 'rejected';

    /** @deprecated Legacy statuses — migrated automatically */
    public const LEGACY_PENDING = 'pending';

    public const LEGACY_SUBMITTED = 'submitted';

    public const LEGACY_MISSING = 'missing';

    public static function all(): array
    {
        return [
            self::NEEDS_UPLOAD,
            self::REVIEW_PENDING,
            self::APPROVED,
            self::REUPLOAD_REQUIRED,
            self::REJECTED,
        ];
    }

    public static function label(string $status): string
    {
        return config("admissions.document_statuses.{$status}.label_ar")
            ?? match ($status) {
                self::LEGACY_SUBMITTED => 'مُقدَّم',
                self::LEGACY_PENDING => 'قيد المراجعة',
                self::LEGACY_MISSING => 'يحتاج رفع',
                default => '—',
            };
    }

    /** @return array<int, string> */
    public static function reviewable(): array
    {
        return [
            self::REVIEW_PENDING,
            self::LEGACY_SUBMITTED,
            self::LEGACY_PENDING,
        ];
    }

    public static function blocksReadiness(string $status): bool
    {
        return $status !== self::APPROVED;
    }
}
