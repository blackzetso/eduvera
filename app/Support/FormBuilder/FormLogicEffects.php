<?php

namespace App\Support\FormBuilder;

class FormLogicEffects
{
    /**
     * @param  array<string, bool>  $visibleByLogic
     * @param  array<string, bool>  $hiddenByLogic
     * @param  array<string, bool>  $requiredByLogic
     * @param  array<string, bool>  $skippedSections
     */
    public function __construct(
        public array $visibleByLogic = [],
        public array $hiddenByLogic = [],
        public array $requiredByLogic = [],
        public array $skippedSections = [],
    ) {}

    public function isSectionSkipped(?string $sectionId): bool
    {
        if ($sectionId === null || $sectionId === '') {
            return false;
        }

        return (bool) ($this->skippedSections[$sectionId] ?? false);
    }

    public function isFieldVisibleByLogic(string $fieldKey): bool
    {
        if ($this->hiddenByLogic[$fieldKey] ?? false) {
            return false;
        }

        if ($this->visibleByLogic[$fieldKey] ?? false) {
            return true;
        }

        return ! array_key_exists($fieldKey, $this->hiddenByLogic)
            && ! array_key_exists($fieldKey, $this->visibleByLogic);
    }

    public function isLogicRequired(string $fieldKey): bool
    {
        return (bool) ($this->requiredByLogic[$fieldKey] ?? false);
    }

    public function isFieldEffective(FormFieldDefinition $field): bool
    {
        if ($field->hidden) {
            return false;
        }

        if ($this->isSectionSkipped($field->sectionId)) {
            return false;
        }

        return $this->isFieldVisibleByLogic($field->key);
    }

    public function isFieldRequired(FormFieldDefinition $field): bool
    {
        if (! $this->isFieldEffective($field)) {
            return false;
        }

        return $field->required || $this->isLogicRequired($field->key);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'visible_by_logic' => $this->visibleByLogic,
            'hidden_by_logic' => $this->hiddenByLogic,
            'required_by_logic' => $this->requiredByLogic,
            'skipped_sections' => $this->skippedSections,
        ];
    }

    public function signature(): string
    {
        return json_encode($this->toArray());
    }

    public function equals(self $other): bool
    {
        return $this->signature() === $other->signature();
    }
}
