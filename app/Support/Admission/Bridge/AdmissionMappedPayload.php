<?php

namespace App\Support\Admission\Bridge;

readonly class AdmissionMappedPayload
{
    /**
     * @param  array<string, mixed>  $normalizedData
     * @param  array<int, string>  $validationErrors
     */
    public function __construct(
        public int $submissionId,
        public int $formId,
        public string $bindingKey,
        public int $mappedFormVersion,
        public string $mappingProfile,
        public array $normalizedData,
        public array $validationErrors = [],
    ) {}

    public function isValid(): bool
    {
        return $this->validationErrors === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'submission_id' => $this->submissionId,
            'form_id' => $this->formId,
            'binding_key' => $this->bindingKey,
            'mapped_form_version' => $this->mappedFormVersion,
            'mapping_profile' => $this->mappingProfile,
            'normalized_data' => $this->normalizedData,
            'validation_errors' => $this->validationErrors,
        ];
    }
}
