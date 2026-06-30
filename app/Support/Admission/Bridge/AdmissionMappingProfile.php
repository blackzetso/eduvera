<?php

namespace App\Support\Admission\Bridge;

readonly class AdmissionMappingProfile
{
    /**
     * @param  array<int, string>  $required
     * @param  array<int, array<int, string>>  $requiredAny
     * @param  array<string, string>  $transforms
     * @param  array<int, array<string, mixed>>  $validators
     */
    public function __construct(
        public string $id,
        public string $version,
        public array $required,
        public array $requiredAny,
        public array $transforms,
        public array $validators,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $profileId, array $config): self
    {
        return new self(
            id: $profileId,
            version: (string) ($config['version'] ?? '1.0.0'),
            required: array_values($config['required'] ?? []),
            requiredAny: array_values($config['required_any'] ?? []),
            transforms: $config['transforms'] ?? [],
            validators: $config['validators'] ?? [],
        );
    }

    public function containsFieldKeys(): bool
    {
        foreach ([$this->required, ...$this->requiredAny] as $paths) {
            foreach ((array) $paths as $path) {
                if (is_string($path) && str_starts_with($path, 'fld_')) {
                    return true;
                }
            }
        }

        foreach (array_keys($this->transforms) as $key) {
            if (str_starts_with((string) $key, 'fld_')) {
                return true;
            }
        }

        return false;
    }
}
