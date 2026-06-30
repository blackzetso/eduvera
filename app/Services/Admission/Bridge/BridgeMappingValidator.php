<?php

namespace App\Services\Admission\Bridge;

use App\Support\Admission\Bridge\AdmissionMappingProfile;

class BridgeMappingValidator
{
    /**
     * @param  array<string, mixed>  $normalizedData
     * @return array<int, string>
     */
    public function validate(AdmissionMappingProfile $profile, array $normalizedData): array
    {
        $errors = [];

        foreach ($profile->required as $path) {
            if (! $this->hasValue($normalizedData, (string) $path)) {
                $errors[] = "Missing required field: {$path}";
            }
        }

        foreach ($profile->requiredAny as $group) {
            $group = array_values((array) $group);
            $satisfied = false;

            foreach ($group as $path) {
                if ($this->hasValue($normalizedData, (string) $path)) {
                    $satisfied = true;
                    break;
                }
            }

            if (! $satisfied && $group !== []) {
                $errors[] = 'Missing required_any group: '.implode(', ', $group);
            }
        }

        foreach ($profile->validators as $validator) {
            $errors = array_merge($errors, $this->applyValidator($validator, $normalizedData));
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $validator
     * @param  array<string, mixed>  $normalizedData
     * @return array<int, string>
     */
    protected function applyValidator(array $validator, array $normalizedData): array
    {
        $rule = (string) ($validator['rule'] ?? '');
        $appliesTo = (string) ($validator['applies_to'] ?? '');

        return match ($rule) {
            'h1_minimum_identity' => $this->validateH1MinimumIdentity($normalizedData, $appliesTo),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $normalizedData
     * @return array<int, string>
     */
    protected function validateH1MinimumIdentity(array $normalizedData, string $section): array
    {
        $name = $this->readPath($normalizedData, "{$section}.name");

        if (! is_string($name) || mb_strlen(trim($name)) < 2) {
            return ["{$section}.name must contain at least 2 characters"];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function hasValue(array $data, string $path): bool
    {
        $value = $this->readPath($data, $path);

        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return true;
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
}
