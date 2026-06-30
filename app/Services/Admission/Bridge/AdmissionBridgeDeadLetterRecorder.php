<?php

namespace App\Services\Admission\Bridge;

use App\Models\Admission\AdmissionBridgeDeadLetter;
use App\Support\Admission\Bridge\AdmissionBindingDefinition;
use App\Support\FormBuilder\FormSubmissionFinalizedPayload;

class AdmissionBridgeDeadLetterRecorder
{
    public function record(
        FormSubmissionFinalizedPayload $event,
        AdmissionBindingDefinition $binding,
        string $errorCode,
        string $errorMessage,
    ): AdmissionBridgeDeadLetter {
        $existing = AdmissionBridgeDeadLetter::query()
            ->where('submission_id', $event->submissionId)
            ->where('error_code', $errorCode)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return AdmissionBridgeDeadLetter::create([
            'submission_id' => $event->submissionId,
            'correlation_id' => $event->correlationId,
            'form_id' => $event->formId,
            'binding_key' => $binding->bindingKey,
            'error_code' => $errorCode,
            'error_message' => mb_substr($errorMessage, 0, 2000),
            'retry_count' => 0,
            'event_payload' => $event->toArray(),
            'failed_at' => now(),
        ]);
    }
}
