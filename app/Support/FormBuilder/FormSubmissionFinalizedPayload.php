<?php

namespace App\Support\FormBuilder;

use App\Models\FormSubmission;
use Illuminate\Support\Str;

readonly class FormSubmissionFinalizedPayload
{
    public const EVENT_NAME = 'form_submission.finalized';

    public const SCHEMA_VERSION = '1.0.0';

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $correlationId,
        public int $submissionId,
        public int $formId,
        public string $status,
        public string $finalizedAt,
        public string $locale,
        public ?string $channel,
        public array $data,
    ) {}

    public static function fromSubmission(
        FormSubmission $submission,
        FormRuntimeContext $context,
    ): self {
        $meta = is_array($submission->data['_meta'] ?? null) ? $submission->data['_meta'] : [];

        return new self(
            correlationId: (string) Str::uuid(),
            submissionId: (int) $submission->id,
            formId: (int) $submission->form_id,
            status: (string) $submission->status,
            finalizedAt: now()->toIso8601String(),
            locale: (string) $submission->locale,
            channel: isset($meta['channel']) ? (string) $meta['channel'] : $context->channel,
            data: $submission->data ?? [],
        );
    }

    public static function shouldEmit(string $status, ?string $previousStatus = null): bool
    {
        if (! self::isFinalizedStatus($status)) {
            return false;
        }

        if ($previousStatus !== null && self::isFinalizedStatus($previousStatus)) {
            return false;
        }

        return true;
    }

    public static function isFinalizedStatus(string $status): bool
    {
        return in_array($status, [
            FormSubmissionStatus::SUBMITTED,
            FormSubmissionStatus::APPROVED,
        ], true);
    }

    public function snapshotFormVersion(): ?int
    {
        $snapshot = FormSubmissionSnapshot::read($this->data);
        $version = $snapshot['form_version'] ?? null;

        return $version !== null ? (int) $version : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event' => self::EVENT_NAME,
            'schema_version' => self::SCHEMA_VERSION,
            'correlation_id' => $this->correlationId,
            'submission_id' => $this->submissionId,
            'form_id' => $this->formId,
            'status' => $this->status,
            'finalized_at' => $this->finalizedAt,
            'locale' => $this->locale,
            'channel' => $this->channel,
            'data' => $this->data,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            correlationId: (string) ($payload['correlation_id'] ?? Str::uuid()),
            submissionId: (int) $payload['submission_id'],
            formId: (int) $payload['form_id'],
            status: (string) ($payload['status'] ?? FormSubmissionStatus::SUBMITTED),
            finalizedAt: (string) ($payload['finalized_at'] ?? now()->toIso8601String()),
            locale: (string) ($payload['locale'] ?? 'ar'),
            channel: isset($payload['channel']) ? (string) $payload['channel'] : null,
            data: is_array($payload['data'] ?? null) ? $payload['data'] : [],
        );
    }
}
