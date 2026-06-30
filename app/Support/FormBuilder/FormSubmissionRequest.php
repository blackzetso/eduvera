<?php

namespace App\Support\FormBuilder;

class FormSubmissionRequest
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public array $data,
        public string $locale = 'ar',
        public string $targetStatus = FormSubmissionStatus::SUBMITTED,
        public ?string $snapshotHash = null,
        public ?int $submissionId = null,
    ) {}

    public function resolvedLocale(): string
    {
        return in_array($this->locale, ['ar', 'en'], true) ? $this->locale : 'ar';
    }

    public function isDraft(): bool
    {
        return $this->targetStatus === FormSubmissionStatus::DRAFT;
    }

    public function isFinal(): bool
    {
        return $this->targetStatus === FormSubmissionStatus::SUBMITTED;
    }
}
