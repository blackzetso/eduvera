<?php

namespace App\Support\FormBuilder;

class FormRuntimePayload
{
    /**
     * @param  array<string, mixed>  $form
     * @param  array<string, mixed>  $settings
     * @param  array<int, array<string, mixed>>  $sections
     * @param  array<int, array<string, mixed>>  $logicRules
     * @param  array<string, bool>  $capabilities
     */
    public function __construct(
        public array $form,
        public array $settings,
        public array $sections,
        public array $logicRules,
        public array $capabilities,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'form' => $this->form,
            'settings' => $this->settings,
            'sections' => $this->sections,
            'logic_rules' => $this->logicRules,
            'capabilities' => $this->capabilities,
        ];
    }

    public function toJson(int $flags = JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->toArray(), $flags);
    }

    public function snapshotHash(): string
    {
        return (string) ($this->form['snapshot_hash'] ?? '');
    }

    public function formId(): int
    {
        return (int) ($this->form['id'] ?? 0);
    }

    /**
     * @return array<int, FormFieldDefinition>
     */
    public function fields(): array
    {
        $fields = [];

        foreach ($this->sections as $section) {
            $sectionId = isset($section['id']) ? (string) $section['id'] : null;

            foreach ($section['fields'] ?? [] as $field) {
                if (! is_array($field)) {
                    continue;
                }

                $fields[] = FormFieldDefinition::fromArray([
                    ...$field,
                    'section_id' => $sectionId,
                ]);
            }
        }

        return $fields;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function sectionFieldIndex(): array
    {
        $index = [];

        foreach ($this->sections as $section) {
            $sectionId = (string) ($section['id'] ?? '');

            if ($sectionId === '') {
                continue;
            }

            $index[$sectionId] = collect($section['fields'] ?? [])
                ->map(fn (array $field) => (string) ($field['key'] ?? ''))
                ->filter()
                ->values()
                ->all();
        }

        return $index;
    }

    /**
     * @return array<int, string>
     */
    public function allFieldKeys(): array
    {
        return collect($this->fields())
            ->map(fn (FormFieldDefinition $field) => $field->key)
            ->values()
            ->all();
    }

    public function workflowEnabled(): bool
    {
        return (bool) ($this->settings['workflow']['enabled'] ?? false);
    }

    public function allowsDraft(): bool
    {
        return (bool) ($this->capabilities['draft'] ?? false);
    }
}
