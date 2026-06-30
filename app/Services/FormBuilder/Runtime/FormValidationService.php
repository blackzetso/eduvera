<?php

namespace App\Services\FormBuilder\Runtime;

use App\Support\FormBuilder\FormFieldDefinition;
use App\Support\FormBuilder\FormLogicEffects;
use App\Support\FormBuilder\FormValidationError;
use App\Support\FormBuilder\FormValidationResult;

class FormValidationService
{
    /**
     * @param  array<int, FormFieldDefinition|array<string, mixed>>  $fields
     * @param  array<string, mixed>  $values
     */
    public function validate(
        array $fields,
        array $values,
        ?FormLogicEffects $effects = null,
        string $locale = 'ar',
    ): FormValidationResult {
        $effects ??= new FormLogicEffects;
        $errors = [];

        foreach ($fields as $field) {
            if (! $field instanceof FormFieldDefinition) {
                $field = FormFieldDefinition::fromArray($field);
            }

            if ($field->readonly || ! $effects->isFieldEffective($field)) {
                continue;
            }

            $fieldErrors = $this->validateField($field, $values[$field->key] ?? null, $effects, $locale);
            $errors = array_merge($errors, $fieldErrors);
        }

        return $errors === []
            ? FormValidationResult::pass()
            : FormValidationResult::fail($errors);
    }

    /**
     * @return array<int, FormValidationError>
     */
    public function validateField(
        FormFieldDefinition $field,
        mixed $value,
        FormLogicEffects $effects,
        string $locale = 'ar',
    ): array {
        $validation = $field->validation;
        $required = $effects->isFieldRequired($field);
        $isEmpty = $this->isEmpty($value, $field->type);

        if ($required && $isEmpty) {
            return [$this->makeError($field->key, 'required', $locale)];
        }

        if ($isEmpty) {
            return [];
        }

        $errors = [];

        foreach ($this->ruleOrder() as $rule) {
            $error = $this->applyRule($rule, $field, $value, $validation, $locale);

            if ($error !== null) {
                $errors[] = $error;
                break;
            }
        }

        return $errors;
    }

    /**
     * @return array<int, string>
     */
    protected function ruleOrder(): array
    {
        return config('form-builder.validation.rule_order', [
            'min_length',
            'max_length',
            'min_value',
            'max_value',
            'regex',
            'email',
            'phone',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validation
     */
    protected function applyRule(
        string $rule,
        FormFieldDefinition $field,
        mixed $value,
        array $validation,
        string $locale,
    ): ?FormValidationError {
        return match ($rule) {
            'min_length' => $this->validateMinLength($field, $value, $validation, $locale),
            'max_length' => $this->validateMaxLength($field, $value, $validation, $locale),
            'min_value' => $this->validateMinValue($field, $value, $validation, $locale),
            'max_value' => $this->validateMaxValue($field, $value, $validation, $locale),
            'regex' => $this->validateRegex($field, $value, $validation, $locale),
            'email' => $this->validateEmail($field, $value, $validation, $locale),
            'phone' => $this->validatePhone($field, $value, $validation, $locale),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $validation
     */
    protected function validateMinLength(
        FormFieldDefinition $field,
        mixed $value,
        array $validation,
        string $locale,
    ): ?FormValidationError {
        $min = $validation['min_length'] ?? null;

        if ($min === null || ! $this->supportsLengthRules($field->type)) {
            return null;
        }

        if (mb_strlen((string) $value) < $min) {
            return $this->makeError($field->key, 'min_length', $locale, ['min' => $min]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $validation
     */
    protected function validateMaxLength(
        FormFieldDefinition $field,
        mixed $value,
        array $validation,
        string $locale,
    ): ?FormValidationError {
        $max = $validation['max_length'] ?? null;

        if ($max === null || ! $this->supportsLengthRules($field->type)) {
            return null;
        }

        if (mb_strlen((string) $value) > $max) {
            return $this->makeError($field->key, 'max_length', $locale, ['max' => $max]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $validation
     */
    protected function validateMinValue(
        FormFieldDefinition $field,
        mixed $value,
        array $validation,
        string $locale,
    ): ?FormValidationError {
        $min = $validation['min_value'] ?? null;

        if ($min === null || ! $this->supportsNumericRules($field->type)) {
            return null;
        }

        if (! is_numeric($value) || $value + 0 < $min + 0) {
            return $this->makeError($field->key, 'min_value', $locale, ['min' => $min]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $validation
     */
    protected function validateMaxValue(
        FormFieldDefinition $field,
        mixed $value,
        array $validation,
        string $locale,
    ): ?FormValidationError {
        $max = $validation['max_value'] ?? null;

        if ($max === null || ! $this->supportsNumericRules($field->type)) {
            return null;
        }

        if (! is_numeric($value) || $value + 0 > $max + 0) {
            return $this->makeError($field->key, 'max_value', $locale, ['max' => $max]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $validation
     */
    protected function validateRegex(
        FormFieldDefinition $field,
        mixed $value,
        array $validation,
        string $locale,
    ): ?FormValidationError {
        $pattern = $validation['regex'] ?? null;

        if ($pattern === null || ! $this->supportsLengthRules($field->type)) {
            return null;
        }

        $pattern = $this->normalizeRegexPattern((string) $pattern);

        if ($pattern === null) {
            return null;
        }

        $matched = @preg_match($pattern, (string) $value);

        if ($matched !== 1) {
            return $this->makeError($field->key, 'regex', $locale);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $validation
     */
    protected function validateEmail(
        FormFieldDefinition $field,
        mixed $value,
        array $validation,
        string $locale,
    ): ?FormValidationError {
        $shouldValidate = ($validation['email'] ?? false) || $field->type === 'email';

        if (! $shouldValidate) {
            return null;
        }

        if (! filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
            return $this->makeError($field->key, 'email', $locale);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $validation
     */
    protected function validatePhone(
        FormFieldDefinition $field,
        mixed $value,
        array $validation,
        string $locale,
    ): ?FormValidationError {
        $shouldValidate = ($validation['phone'] ?? false) || $field->type === 'phone';

        if (! $shouldValidate) {
            return null;
        }

        $pattern = (string) config('form-builder.validation.phone_pattern', '/^\+?[0-9\s\-()]{7,20}$/');

        if (! preg_match($pattern, (string) $value)) {
            return $this->makeError($field->key, 'phone', $locale);
        }

        return null;
    }

    public function isEmpty(mixed $value, string $type): bool
    {
        if ($value === null) {
            return true;
        }

        if (in_array($type, ['multi_select', 'checkbox'], true)) {
            return ! is_array($value) || $value === [];
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }

    protected function supportsLengthRules(string $type): bool
    {
        return in_array($type, [
            'text', 'textarea', 'email', 'phone', 'url', 'date', 'time',
        ], true);
    }

    protected function supportsNumericRules(string $type): bool
    {
        return in_array($type, ['number', 'slider', 'rating'], true);
    }

    protected function normalizeRegexPattern(string $pattern): ?string
    {
        $pattern = trim($pattern);

        if ($pattern === '') {
            return null;
        }

        if (! str_starts_with($pattern, '/')) {
            $pattern = '/'.$pattern.'/u';
        }

        return @preg_match($pattern, '') === false ? null : $pattern;
    }

    /**
     * @param  array<string, scalar|null>  $replacements
     */
    protected function makeError(
        string $fieldKey,
        string $rule,
        string $locale,
        array $replacements = [],
    ): FormValidationError {
        $messages = config("form-builder.validation.messages.$rule", []);
        $messageAr = $this->formatMessage((string) ($messages['ar'] ?? 'قيمة غير صالحة'), $replacements);
        $messageEn = $this->formatMessage((string) ($messages['en'] ?? 'Invalid value'), $replacements);

        return new FormValidationError($fieldKey, $rule, $messageAr, $messageEn);
    }

    /**
     * @param  array<string, scalar|null>  $replacements
     */
    protected function formatMessage(string $message, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $message = str_replace(':'.$key, (string) $value, $message);
        }

        return $message;
    }
}
