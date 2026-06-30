<?php

namespace App\Services\Admission\Bridge;

use App\Models\Admission\AdmissionBridgeRun;
use App\Support\Admission\Bridge\AdmissionBindingDefinition;
use App\Support\Admission\Bridge\BridgeRunOutcome;
use App\Support\Admission\Bridge\BridgeRunStatus;
use App\Support\FormBuilder\FormSubmissionFinalizedPayload;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class AdmissionBridgeRunRecorder
{
    public function findBySubmissionId(int $submissionId): ?AdmissionBridgeRun
    {
        return AdmissionBridgeRun::query()
            ->where('submission_id', $submissionId)
            ->first();
    }

    public function isTerminal(AdmissionBridgeRun $run): bool
    {
        return in_array($run->status, [
            BridgeRunStatus::COMPLETED,
            BridgeRunStatus::FAILED,
            BridgeRunStatus::SKIPPED,
        ], true);
    }

    public function recordSkipped(
        FormSubmissionFinalizedPayload $event,
        AdmissionBindingDefinition $binding,
        string $inactiveReason,
        string $errorCode,
    ): AdmissionBridgeRun {
        return $this->upsertRun($event, $binding, [
            'status' => BridgeRunStatus::SKIPPED,
            'outcome' => BridgeRunOutcome::NO_OP,
            'error_code' => $errorCode,
            'processed_at' => now(),
            'duration_ms' => 0,
        ], [
            'inactive_reason' => $inactiveReason,
        ]);
    }

    public function recordFailed(
        FormSubmissionFinalizedPayload $event,
        AdmissionBindingDefinition $binding,
        string $errorCode,
        ?string $errorMessage = null,
        ?int $durationMs = null,
    ): AdmissionBridgeRun {
        return $this->upsertRun($event, $binding, [
            'status' => BridgeRunStatus::FAILED,
            'outcome' => null,
            'error_code' => $errorCode,
            'processed_at' => now(),
            'duration_ms' => $durationMs ?? 0,
        ], [
            'error_message' => $errorMessage,
        ]);
    }

    public function recordPending(
        FormSubmissionFinalizedPayload $event,
        AdmissionBindingDefinition $binding,
    ): AdmissionBridgeRun {
        return $this->upsertRun($event, $binding, [
            'status' => BridgeRunStatus::PENDING,
            'outcome' => null,
            'error_code' => null,
            'processed_at' => null,
            'duration_ms' => null,
        ]);
    }

    public function recordCompleted(
        FormSubmissionFinalizedPayload $event,
        AdmissionBindingDefinition $binding,
        int $admissionCaseId,
        string $outcome,
        int $durationMs,
    ): AdmissionBridgeRun {
        return $this->upsertRun($event, $binding, [
            'status' => BridgeRunStatus::COMPLETED,
            'outcome' => $outcome,
            'admission_case_id' => $admissionCaseId,
            'error_code' => null,
            'processed_at' => now(),
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $logContext
     */
    protected function upsertRun(
        FormSubmissionFinalizedPayload $event,
        AdmissionBindingDefinition $binding,
        array $attributes,
        array $logContext = [],
    ): AdmissionBridgeRun {
        $existing = $this->findBySubmissionId($event->submissionId);

        if ($existing !== null) {
            if ($this->isTerminal($existing)) {
                return $existing;
            }

            $existing->update($attributes);

            return $existing->fresh();
        }

        $base = [
            'submission_id' => $event->submissionId,
            'correlation_id' => $event->correlationId,
            'form_id' => $event->formId,
            'binding_key' => $binding->bindingKey,
            'mapped_form_version' => $binding->mappedFormVersion ?? $event->snapshotFormVersion() ?? 0,
            'mapping_profile' => $binding->mappingProfile,
        ];

        try {
            return AdmissionBridgeRun::create(array_merge($base, $attributes));
        } catch (QueryException $exception) {
            if (! $this->isUniqueSubmissionViolation($exception)) {
                throw $exception;
            }

            $existing = $this->findBySubmissionId($event->submissionId);

            if ($existing === null) {
                throw $exception;
            }

            Log::warning('Admission bridge run idempotency conflict resolved by reading existing run.', array_merge([
                'submission_id' => $event->submissionId,
                'correlation_id' => $event->correlationId,
            ], $logContext));

            return $existing;
        }
    }

    protected function isUniqueSubmissionViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique')
            && str_contains($message, 'submission_id');
    }
}
