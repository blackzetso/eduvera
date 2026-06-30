<?php

namespace App\Services\Admission\Bridge;

use App\Support\Admission\Bridge\AdmissionBindingDefinition;
use App\Support\Admission\Bridge\AdmissionBridgeConfig;
use App\Support\Admission\Bridge\AdmissionMappedPayload;
use App\Support\Admission\Bridge\AdmissionMappingProfile;
use App\Support\FormBuilder\FormSubmissionFinalizedPayload;

class AdmissionMappingEngine
{
    public function __construct(
        protected AdmissionBridgeConfig $config,
        protected BridgeMappingTransformApplicator $transforms,
        protected BridgeMappingValidator $validator,
    ) {}

    public function map(
        FormSubmissionFinalizedPayload $event,
        AdmissionBindingDefinition $binding,
    ): AdmissionMappedPayload {
        $profile = $this->config->mappingProfile($binding->mappingProfile);
        $errors = [];

        if ($profile === null) {
            $errors[] = 'Mapping profile not found: '.$binding->mappingProfile;

            return $this->payload($event, $binding, [], $errors);
        }

        $fieldMapErrors = $this->validateFieldMap($binding, $profile);
        if ($fieldMapErrors !== []) {
            return $this->payload($event, $binding, [], $fieldMapErrors);
        }

        $normalized = $this->extractMappedValues($event, $binding->fieldMap);
        $normalized = $this->applyTransforms($profile, $normalized);
        $validationErrors = $this->validator->validate($profile, $normalized);

        return $this->payload($event, $binding, $normalized, $validationErrors);
    }

    /**
     * @param  array<string, string>  $fieldMap
     * @return array<string, mixed>
     */
    protected function extractMappedValues(
        FormSubmissionFinalizedPayload $event,
        array $fieldMap,
    ): array {
        $submissionValues = $this->submissionFieldValues($event->data);
        $normalized = [];

        foreach ($fieldMap as $semanticPath => $fieldKey) {
            $value = $submissionValues[$fieldKey] ?? null;
            $this->writePath($normalized, $semanticPath, $value);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function submissionFieldValues(array $data): array
    {
        $values = [];

        foreach ($data as $key => $value) {
            if ($key === '_meta' || ! is_string($key)) {
                continue;
            }

            $values[$key] = $value;
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $target
     */
    protected function writePath(array &$target, string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $cursor = &$target;

        foreach ($segments as $index => $segment) {
            if ($index === count($segments) - 1) {
                $cursor[$segment] = $value;

                return;
            }

            if (! isset($cursor[$segment]) || ! is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }

            $cursor = &$cursor[$segment];
        }
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    protected function applyTransforms(AdmissionMappingProfile $profile, array $normalized): array
    {
        foreach ($profile->transforms as $path => $transform) {
            $value = $this->readPath($normalized, (string) $path);

            if ($value === null) {
                continue;
            }

            $this->writePath($normalized, (string) $path, $this->transforms->apply((string) $transform, $value));
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    protected function validateFieldMap(
        AdmissionBindingDefinition $binding,
        AdmissionMappingProfile $profile,
    ): array {
        $errors = [];
        $requiredAnyPaths = array_merge(
            [],
            ...array_map(fn ($group) => array_values((array) $group), $profile->requiredAny),
        );

        $requiredPaths = array_merge(
            $profile->required,
            $requiredAnyPaths,
            array_keys($profile->transforms),
        );

        foreach (array_unique($requiredPaths) as $path) {
            if (! array_key_exists($path, $binding->fieldMap)) {
                $errors[] = "Missing field_map entry for: {$path}";
            }
        }

        if ($binding->fieldMap === []) {
            $errors[] = 'Binding field_map is empty';
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function readPath(array $data, string $path): mixed
    {
        $segments = explode('.', $path);
        $cursor = $data;

        foreach ($segments as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    /**
     * @param  array<string, mixed>  $normalizedData
     * @param  array<int, string>  $validationErrors
     */
    protected function payload(
        FormSubmissionFinalizedPayload $event,
        AdmissionBindingDefinition $binding,
        array $normalizedData,
        array $validationErrors,
    ): AdmissionMappedPayload {
        return new AdmissionMappedPayload(
            submissionId: $event->submissionId,
            formId: $event->formId,
            bindingKey: $binding->bindingKey,
            mappedFormVersion: $binding->mappedFormVersion ?? $event->snapshotFormVersion() ?? 0,
            mappingProfile: $binding->mappingProfile,
            normalizedData: $normalizedData,
            validationErrors: array_values($validationErrors),
        );
    }
}
