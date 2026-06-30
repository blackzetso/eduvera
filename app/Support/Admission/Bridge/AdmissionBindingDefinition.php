<?php

namespace App\Support\Admission\Bridge;

readonly class AdmissionBindingDefinition
{
    /**
     * @param  array<string, string>  $fieldMap
     */
    public function __construct(
        public string $bindingKey,
        public bool $enabled,
        public ?int $formId,
        public ?int $mappedFormVersion,
        public string $mappingProfile,
        public array $fieldMap,
        public string $intakeRole = 'campus_visit',
        public string $targetPhase = 'evaluate_activity',
        public ?string $evaluateActivityType = 'campus_visit',
        public string $v1PipelineStage = 'campus_visit',
        public bool $createsCase = true,
        public bool $createsHousehold = false,
        public string $duplicatePolicy = 'same_cycle_link',
        public string $cycleScope = 'academic_year',
        public string $sourceChannel = 'form_builder',
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $configKey, array $config): self
    {
        $fieldMap = $config['field_map'] ?? [];

        if (! is_array($fieldMap)) {
            $fieldMap = [];
        }

        $formId = $config['form_id'] ?? null;

        return new self(
            bindingKey: (string) ($config['binding_key'] ?? $configKey),
            enabled: (bool) ($config['enabled'] ?? false),
            formId: $formId !== null ? (int) $formId : null,
            mappedFormVersion: isset($config['mapped_form_version'])
                ? (int) $config['mapped_form_version']
                : null,
            mappingProfile: (string) ($config['mapping_profile'] ?? ''),
            fieldMap: array_map('strval', $fieldMap),
            intakeRole: (string) ($config['intake_role'] ?? 'campus_visit'),
            targetPhase: (string) ($config['target_phase'] ?? 'evaluate_activity'),
            evaluateActivityType: $config['evaluate_activity_type'] ?? null,
            v1PipelineStage: (string) ($config['v1_pipeline_stage'] ?? 'campus_visit'),
            createsCase: (bool) ($config['creates_case'] ?? true),
            createsHousehold: (bool) ($config['creates_household'] ?? false),
            duplicatePolicy: (string) ($config['duplicate_policy'] ?? 'same_cycle_link'),
            cycleScope: (string) ($config['cycle_scope'] ?? 'academic_year'),
            sourceChannel: (string) ($config['source_channel'] ?? 'form_builder'),
        );
    }
}
