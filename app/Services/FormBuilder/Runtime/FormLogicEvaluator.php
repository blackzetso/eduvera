<?php

namespace App\Services\FormBuilder\Runtime;

use App\Support\FormBuilder\FormLogicEffects;

class FormLogicEvaluator
{
    /**
     * @param  array<int, array<string, mixed>>  $rules
     * @param  array<string, mixed>  $values
     * @param  array<string, array<int, string>>  $sectionFieldIndex
     * @param  array<int, string>  $allFieldKeys
     */
    public function evaluate(
        array $rules,
        array $values,
        array $sectionFieldIndex = [],
        array $allFieldKeys = [],
    ): FormLogicEffects {
        $allFieldKeys = $this->resolveFieldKeys($allFieldKeys, $sectionFieldIndex, $rules);
        $maxPasses = (int) config('form-builder.logic.max_passes', 10);

        $effects = $this->evaluatePass($rules, $values, $sectionFieldIndex, $allFieldKeys);

        for ($pass = 1; $pass < $maxPasses; $pass++) {
            $next = $this->evaluatePass($rules, $values, $sectionFieldIndex, $allFieldKeys);

            if ($next->equals($effects)) {
                return $next;
            }

            $effects = $next;
        }

        return $effects;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rules
     * @param  array<string, mixed>  $values
     * @param  array<string, array<int, string>>  $sectionFieldIndex
     * @param  array<int, string>  $allFieldKeys
     */
    protected function evaluatePass(
        array $rules,
        array $values,
        array $sectionFieldIndex,
        array $allFieldKeys,
    ): FormLogicEffects {
        $effects = new FormLogicEffects;

        foreach ($rules as $rule) {
            if (! $this->isConditionMet($rule, $values)) {
                continue;
            }

            $this->applyAction($effects, $rule, $sectionFieldIndex);
        }

        return $effects;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $values
     */
    public function isConditionMet(array $rule, array $values): bool
    {
        $fieldKey = (string) ($rule['field_key'] ?? '');

        if ($fieldKey === '') {
            return false;
        }

        $operator = (string) ($rule['operator'] ?? 'equals');
        $expected = $rule['value'] ?? null;
        $actual = $values[$fieldKey] ?? null;

        return match ($operator) {
            'not_equals' => ! $this->valuesEqual($actual, $expected),
            'contains' => $this->valueContains($actual, $expected),
            default => $this->valuesEqual($actual, $expected),
        };
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, array<int, string>>  $sectionFieldIndex
     */
    protected function applyAction(
        FormLogicEffects $effects,
        array $rule,
        array $sectionFieldIndex,
    ): void {
        $action = (string) ($rule['action'] ?? '');

        match ($action) {
            'show' => $this->applyShow($effects, $rule),
            'hide' => $this->applyHide($effects, $rule),
            'require' => $this->applyRequire($effects, $rule),
            'skip_section' => $this->applySkipSection($effects, $rule, $sectionFieldIndex),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function applyShow(FormLogicEffects $effects, array $rule): void
    {
        $target = $this->resolveFieldTarget($rule);

        if ($target === null) {
            return;
        }

        if (! ($effects->hiddenByLogic[$target] ?? false)) {
            $effects->visibleByLogic[$target] = true;
        }
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function applyHide(FormLogicEffects $effects, array $rule): void
    {
        $target = $this->resolveFieldTarget($rule);

        if ($target === null) {
            return;
        }

        $effects->hiddenByLogic[$target] = true;
        unset($effects->visibleByLogic[$target]);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function applyRequire(FormLogicEffects $effects, array $rule): void
    {
        $target = $this->resolveFieldTarget($rule);

        if ($target === null) {
            return;
        }

        $effects->requiredByLogic[$target] = true;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, array<int, string>>  $sectionFieldIndex
     */
    protected function applySkipSection(
        FormLogicEffects $effects,
        array $rule,
        array $sectionFieldIndex,
    ): void {
        $sectionId = $this->resolveSectionTarget($rule);

        if ($sectionId === null) {
            return;
        }

        $effects->skippedSections[$sectionId] = true;

        foreach ($sectionFieldIndex[$sectionId] ?? [] as $fieldKey) {
            $effects->hiddenByLogic[$fieldKey] = true;
            unset($effects->visibleByLogic[$fieldKey]);
        }
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function resolveFieldTarget(array $rule): ?string
    {
        $target = trim((string) ($rule['target_field_key'] ?? ''));

        return $target === '' ? null : $target;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function resolveSectionTarget(array $rule): ?string
    {
        $sectionId = $rule['target_section_id'] ?? null;

        if (is_string($sectionId) && $sectionId !== '') {
            return $sectionId;
        }

        $target = trim((string) ($rule['target_field_key'] ?? ''));

        if (str_starts_with($target, 'sec_')) {
            return $target;
        }

        return null;
    }

    protected function valuesEqual(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual)) {
            return in_array($expected, $actual, ! is_numeric($expected));
        }

        if (is_bool($actual)) {
            return $actual === filter_var($expected, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        if (is_numeric($actual) && is_numeric($expected)) {
            return (string) $actual === (string) $expected;
        }

        return (string) $actual === (string) $expected;
    }

    protected function valueContains(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual)) {
            return in_array($expected, $actual, ! is_numeric($expected))
                || collect($actual)->contains(fn ($item) => $this->valueContains((string) $item, $expected));
        }

        if ($actual === null) {
            return false;
        }

        return str_contains((string) $actual, (string) $expected);
    }

    /**
     * @param  array<int, string>  $allFieldKeys
     * @param  array<string, array<int, string>>  $sectionFieldIndex
     * @param  array<int, array<string, mixed>>  $rules
     * @return array<int, string>
     */
    protected function resolveFieldKeys(
        array $allFieldKeys,
        array $sectionFieldIndex,
        array $rules,
    ): array {
        if ($allFieldKeys !== []) {
            return array_values(array_unique($allFieldKeys));
        }

        $keys = collect($sectionFieldIndex)->flatten()->filter()->values();

        foreach ($rules as $rule) {
            foreach (['field_key', 'target_field_key'] as $property) {
                $value = trim((string) ($rule[$property] ?? ''));

                if ($value !== '' && ! str_starts_with($value, 'sec_')) {
                    $keys->push($value);
                }
            }
        }

        return $keys->unique()->values()->all();
    }
}
