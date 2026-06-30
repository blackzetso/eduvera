<?php

namespace App\Services\Admission\Bridge;

use App\Exceptions\Admission\BridgeBindingAmbiguousException;
use App\Support\Admission\Bridge\AdmissionBindingDefinition;
use App\Support\Admission\Bridge\AdmissionBridgeConfig;
use App\Support\Admission\Bridge\AdmissionBridgeConsumerResult;
use App\Support\Admission\Bridge\AdmissionMappedPayload;
use App\Support\Admission\Bridge\BridgeErrorCode;
use App\Support\Admission\Bridge\BridgeFormVersionGuard;
use App\Support\FormBuilder\FormSubmissionFinalizedPayload;
use Illuminate\Support\Facades\Log;

class AdmissionBridgeConsumer
{
    public function __construct(
        protected AdmissionBridgeConfig $config,
        protected AdmissionBindingResolver $bindingResolver,
        protected BridgeFormVersionGuard $versionGuard,
        protected AdmissionBridgeRunRecorder $runRecorder,
        protected AdmissionBridgeDeadLetterRecorder $deadLetterRecorder,
        protected AdmissionMappingEngine $mappingEngine,
        protected BridgeCampusVisitCaseOrchestrator $orchestrator,
    ) {}

    public function consume(FormSubmissionFinalizedPayload $event): AdmissionBridgeConsumerResult
    {
        if (! $this->config->enabled()) {
            return AdmissionBridgeConsumerResult::ignored('bridge_globally_disabled');
        }

        try {
            $this->bindingResolver->assertUniqueEnabledFormIds();
        } catch (BridgeBindingAmbiguousException $exception) {
            Log::critical('Admission bridge refused to process event due to ambiguous binding configuration.', [
                'form_id' => $exception->formId,
                'binding_keys' => $exception->bindingKeys,
                'submission_id' => $event->submissionId,
                'correlation_id' => $event->correlationId,
                'error_code' => $exception->errorCode(),
            ]);

            return AdmissionBridgeConsumerResult::ignored('binding_ambiguous');
        }

        $existing = $this->runRecorder->findBySubmissionId($event->submissionId);

        if ($existing !== null && $this->runRecorder->isTerminal($existing)) {
            return AdmissionBridgeConsumerResult::resumed($existing);
        }

        $resolution = $this->bindingResolver->resolveByFormId($event->formId);

        if ($resolution->isNotFound()) {
            return AdmissionBridgeConsumerResult::ignored('binding_not_found');
        }

        $binding = $resolution->binding;

        if ($binding === null) {
            return AdmissionBridgeConsumerResult::ignored('binding_not_found');
        }

        if ($resolution->isInactive()) {
            $run = $this->runRecorder->recordSkipped(
                $event,
                $binding,
                (string) $resolution->inactiveReason,
                (string) $resolution->errorCode,
            );

            return AdmissionBridgeConsumerResult::skipped($run, $resolution->inactiveReason);
        }

        $snapshotVersion = $event->snapshotFormVersion();

        if ($snapshotVersion === null || ! $this->versionGuard->matches($binding, $snapshotVersion)) {
            return $this->handleVersionMismatch($event, $binding, $snapshotVersion);
        }

        $startedAt = microtime(true);
        $this->runRecorder->recordPending($event, $binding);

        $mapped = $this->mappingEngine->map($event, $binding);

        if (! $mapped->isValid()) {
            return $this->handleMappingFailure($event, $binding, $mapped, $startedAt);
        }

        $orchestration = $this->orchestrator->orchestrate($mapped, $event, $binding);

        if (! $orchestration->success) {
            return $this->handleOrchestrationFailure(
                $event,
                $binding,
                (string) $orchestration->errorCode,
                (string) $orchestration->errorMessage,
                $startedAt,
            );
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $run = $this->runRecorder->recordCompleted(
            $event,
            $binding,
            (int) $orchestration->admissionCaseId,
            (string) $orchestration->outcome,
            $durationMs,
        );

        return AdmissionBridgeConsumerResult::completed($run);
    }

    protected function handleMappingFailure(
        FormSubmissionFinalizedPayload $event,
        AdmissionBindingDefinition $binding,
        AdmissionMappedPayload $mapped,
        float $startedAt,
    ): AdmissionBridgeConsumerResult {
        $errorCode = $this->resolveMappingErrorCode($mapped);
        $message = implode('; ', $mapped->validationErrors);

        return $this->handleFailure($event, $binding, $errorCode, $message, $startedAt);
    }

    protected function handleOrchestrationFailure(
        FormSubmissionFinalizedPayload $event,
        AdmissionBindingDefinition $binding,
        string $errorCode,
        string $errorMessage,
        float $startedAt,
    ): AdmissionBridgeConsumerResult {
        return $this->handleFailure($event, $binding, $errorCode, $errorMessage, $startedAt);
    }

    protected function handleFailure(
        FormSubmissionFinalizedPayload $event,
        AdmissionBindingDefinition $binding,
        string $errorCode,
        string $errorMessage,
        float $startedAt,
    ): AdmissionBridgeConsumerResult {
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        $run = $this->runRecorder->recordFailed(
            $event,
            $binding,
            $errorCode,
            $errorMessage,
            $durationMs,
        );

        if ($this->config->dlqEnabled()) {
            $this->deadLetterRecorder->record($event, $binding, $errorCode, $errorMessage);
        }

        return AdmissionBridgeConsumerResult::deadLettered($run);
    }

    protected function resolveMappingErrorCode(AdmissionMappedPayload $mapped): string
    {
        foreach ($mapped->validationErrors as $error) {
            if (str_contains($error, 'Mapping profile not found')) {
                return BridgeErrorCode::MAP_PROFILE_NOT_FOUND;
            }

            if (str_contains($error, 'field_map')) {
                return BridgeErrorCode::MAP_INCOMPLETE;
            }
        }

        return BridgeErrorCode::MAP_VALIDATION_FAILED;
    }

    protected function handleVersionMismatch(
        FormSubmissionFinalizedPayload $event,
        AdmissionBindingDefinition $binding,
        ?int $snapshotVersion,
    ): AdmissionBridgeConsumerResult {
        $expected = $this->versionGuard->expectedVersion($binding);
        $message = sprintf(
            'Form version mismatch for binding %s: expected %s, received %s',
            $binding->bindingKey,
            $expected ?? 'null',
            $snapshotVersion ?? 'null',
        );

        if ($this->config->autoDisableOnVersionMismatch()) {
            Log::critical('Admission bridge binding should be disabled after version mismatch.', [
                'binding_key' => $binding->bindingKey,
                'form_id' => $event->formId,
                'expected_version' => $expected,
                'received_version' => $snapshotVersion,
            ]);
        }

        $run = $this->runRecorder->recordFailed(
            $event,
            $binding,
            $this->versionGuard->mismatchErrorCode(),
            $message,
        );

        if ($this->config->dlqEnabled()) {
            $this->deadLetterRecorder->record(
                $event,
                $binding,
                BridgeErrorCode::MAP_VERSION_MISMATCH,
                $message,
            );
        }

        return AdmissionBridgeConsumerResult::deadLettered($run);
    }
}
