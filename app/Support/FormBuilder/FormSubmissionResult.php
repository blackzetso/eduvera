<?php

namespace App\Support\FormBuilder;

use App\Models\FormSubmission;

class FormSubmissionResult
{
    public function __construct(
        public FormSubmission $submission,
        public FormValidationResult $validation,
        public FormLogicEffects $logicEffects,
    ) {}

    public function isValid(): bool
    {
        return $this->validation->valid;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $locale = 'ar'): array
    {
        return [
            'submission' => [
                'id' => $this->submission->id,
                'form_id' => $this->submission->form_id,
                'status' => $this->submission->status,
                'workflow_stage' => $this->submission->workflow_stage,
                'locale' => $this->submission->locale,
                'created_at' => $this->submission->created_at?->toIso8601String(),
                'updated_at' => $this->submission->updated_at?->toIso8601String(),
            ],
            'validation' => $this->validation->toArray($locale),
            'logic_effects' => $this->logicEffects->toArray(),
        ];
    }
}
