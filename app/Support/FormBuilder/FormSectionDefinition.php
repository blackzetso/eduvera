<?php

namespace App\Support\FormBuilder;

class FormSectionDefinition
{
    /**
     * @param  array<int, string>  $fieldKeys
     */
    public function __construct(
        public string $id,
        public array $fieldKeys = [],
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<string, self>
     */
    public static function mapFromArrays(array $sections): array
    {
        $mapped = [];

        foreach ($sections as $section) {
            $id = (string) ($section['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $fieldKeys = collect($section['fields'] ?? [])
                ->map(fn (array $field) => (string) ($field['key'] ?? $field['id'] ?? ''))
                ->filter()
                ->values()
                ->all();

            $mapped[$id] = new self($id, $fieldKeys);
        }

        return $mapped;
    }

    /**
     * @param  array<int, FormFieldDefinition>  $fields
     * @return array<string, self>
     */
    public static function mapFromFields(array $fields): array
    {
        $mapped = [];

        foreach ($fields as $field) {
            if ($field->sectionId === null) {
                continue;
            }

            if (! isset($mapped[$field->sectionId])) {
                $mapped[$field->sectionId] = new self($field->sectionId, []);
            }

            $mapped[$field->sectionId]->fieldKeys[] = $field->key;
        }

        return $mapped;
    }
}
