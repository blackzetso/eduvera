<?php

namespace App\Support\FormBuilder;

class FormFieldDefinition
{
    /**
     * @param  array<string, mixed>  $validation
     */
    public function __construct(
        public string $key,
        public string $type,
        public ?string $sectionId = null,
        public bool $required = false,
        public bool $hidden = false,
        public bool $readonly = false,
        public array $validation = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $validation = $data['validation'] ?? [];

        if ($validation === [] && isset($data['schema']['validation'])) {
            $validation = $data['schema']['validation'];
        }

        $hidden = (bool) ($data['hidden'] ?? false);

        if (! $hidden && isset($data['schema']['visibility']['mode'])) {
            $hidden = $data['schema']['visibility']['mode'] === 'hidden';
        }

        $required = (bool) ($data['required'] ?? ($validation['required'] ?? false));

        return new self(
            key: (string) ($data['key'] ?? $data['id'] ?? ''),
            type: (string) ($data['type'] ?? 'text'),
            sectionId: isset($data['section_id']) ? (string) $data['section_id'] : null,
            required: $required,
            hidden: $hidden,
            readonly: (bool) ($data['readonly'] ?? (($data['schema']['visibility']['mode'] ?? null) === 'readonly')),
            validation: self::normalizeValidation($validation),
        );
    }

    /**
     * @param  array<string, mixed>  $validation
     * @return array<string, mixed>
     */
    public static function normalizeValidation(array $validation): array
    {
        return [
            'required' => (bool) ($validation['required'] ?? false),
            'min_length' => self::nullableInt($validation['min_length'] ?? null),
            'max_length' => self::nullableInt($validation['max_length'] ?? null),
            'min_value' => self::nullableNumeric($validation['min_value'] ?? null),
            'max_value' => self::nullableNumeric($validation['max_value'] ?? null),
            'regex' => self::nullableString($validation['regex'] ?? null),
            'email' => (bool) ($validation['email'] ?? false),
            'phone' => (bool) ($validation['phone'] ?? false),
        ];
    }

    protected static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    protected static function nullableNumeric(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? $value + 0 : null;
    }

    protected static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
