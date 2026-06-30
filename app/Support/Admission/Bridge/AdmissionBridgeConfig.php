<?php

namespace App\Support\Admission\Bridge;

use Illuminate\Support\Collection;

class AdmissionBridgeConfig
{
    public function enabled(): bool
    {
        return (bool) config('admissions_bridge.enabled', false);
    }

    public function processingMode(): string
    {
        return (string) config('admissions_bridge.processing_mode', 'queue');
    }

    public function queueName(): string
    {
        return (string) config('admissions_bridge.queue_name', 'admissions-bridge');
    }

    public function dlqEnabled(): bool
    {
        return (bool) config('admissions_bridge.dlq_enabled', true);
    }

    public function autoDisableOnVersionMismatch(): bool
    {
        return (bool) config('admissions_bridge.auto_disable_on_version_mismatch', false);
    }

    /**
     * @return Collection<string, AdmissionBindingDefinition>
     */
    public function bindings(): Collection
    {
        $raw = config('admissions_intake_bindings', []);

        return collect($raw)->mapWithKeys(
            fn (array $binding, string $key) => [
                $key => AdmissionBindingDefinition::fromConfig($key, $binding),
            ],
        );
    }

    public function binding(string $bindingKey): ?AdmissionBindingDefinition
    {
        $raw = config("admissions_intake_bindings.{$bindingKey}");

        if (! is_array($raw)) {
            return null;
        }

        return AdmissionBindingDefinition::fromConfig($bindingKey, $raw);
    }

    public function mappingProfile(string $profileId): ?AdmissionMappingProfile
    {
        $raw = config("admissions_mapping_profiles.{$profileId}");

        if (! is_array($raw)) {
            return null;
        }

        return AdmissionMappingProfile::fromConfig($profileId, $raw);
    }

    /**
     * @return Collection<string, AdmissionMappingProfile>
     */
    public function mappingProfiles(): Collection
    {
        $raw = config('admissions_mapping_profiles', []);

        return collect($raw)->mapWithKeys(
            fn (array $profile, string $key) => [
                $key => AdmissionMappingProfile::fromConfig($key, $profile),
            ],
        );
    }
}
