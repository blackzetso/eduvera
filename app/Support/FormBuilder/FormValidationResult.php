<?php

namespace App\Support\FormBuilder;

class FormValidationResult
{
    /**
     * @param  array<int, FormValidationError>  $errors
     */
    public function __construct(
        public bool $valid,
        public array $errors = [],
    ) {}

    public static function pass(): self
    {
        return new self(true);
    }

    /**
     * @param  array<int, FormValidationError>  $errors
     */
    public static function fail(array $errors): self
    {
        return new self(false, $errors);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function errorsForLocale(string $locale = 'ar'): array
    {
        return array_map(
            fn (FormValidationError $error) => $error->toArray($locale),
            $this->errors,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $locale = 'ar'): array
    {
        return [
            'valid' => $this->valid,
            'errors' => $this->errorsForLocale($locale),
        ];
    }
}
