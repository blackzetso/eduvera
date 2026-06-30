<?php

namespace App\Services\FormBuilder;

use App\Models\Form;
use App\Models\FormInput;
use App\Models\FormSection;
use App\Services\Translation\BilingualAutoTranslationService;
use Illuminate\Support\Facades\DB;

class FormBuilderPersistenceService
{
    public function __construct(
        protected BilingualAutoTranslationService $translator,
    ) {}

    public function store(array $payload): Form
    {
        $payload = $this->translator->translatePayload($payload);

        return DB::transaction(function () use ($payload) {
            $form = Form::create([
                'name' => $payload['name'],
                'name_en' => $payload['name_en'] ?? null,
                'description_ar' => $payload['description_ar'] ?? null,
                'description_en' => $payload['description_en'] ?? null,
                'status' => $payload['status'] ?? 'enable',
                'publication_status' => $payload['publication_status'] ?? 'draft',
                'version' => config('form-builder.version', 2),
                'template_key' => $payload['template_key'] ?? null,
                'visibility_settings' => $payload['visibility_settings'] ?? null,
                'submission_settings' => $payload['submission_settings'] ?? null,
                'workflow_definition' => $payload['workflow_definition'] ?? null,
                'logic_rules' => $payload['logic_rules'] ?? null,
                'builder_settings' => $payload['builder_settings'] ?? null,
            ]);

            $this->syncStructure($form, $payload);

            return $form->load(['sections.inputs']);
        });
    }

    public function update(Form $form, array $payload): Form
    {
        $payload = $this->translator->translatePayload($payload);

        return DB::transaction(function () use ($form, $payload) {
            $form->update([
                'name' => $payload['name'] ?? $form->name,
                'name_en' => $payload['name_en'] ?? $form->name_en,
                'description_ar' => $payload['description_ar'] ?? $form->description_ar,
                'description_en' => $payload['description_en'] ?? $form->description_en,
                'publication_status' => $payload['publication_status'] ?? $form->publication_status,
                'template_key' => $payload['template_key'] ?? $form->template_key,
                'visibility_settings' => $payload['visibility_settings'] ?? $form->visibility_settings,
                'submission_settings' => $payload['submission_settings'] ?? $form->submission_settings,
                'workflow_definition' => $payload['workflow_definition'] ?? $form->workflow_definition,
                'logic_rules' => $payload['logic_rules'] ?? $form->logic_rules,
                'builder_settings' => $payload['builder_settings'] ?? $form->builder_settings,
            ]);

            $form->inputs()->delete();
            $form->sections()->delete();

            $this->syncStructure($form, $payload);

            return $form->fresh(['sections.inputs']);
        });
    }

    protected function syncStructure(Form $form, array $payload): void
    {
        $sections = $payload['sections'] ?? [];
        $legacyInputs = $payload['inputs'] ?? [];

        if (empty($sections) && ! empty($legacyInputs)) {
            $sections = [[
                'title_ar' => 'الحقول العامة',
                'title_en' => 'General Fields',
                'description_ar' => null,
                'description_en' => null,
                'fields' => $legacyInputs,
            ]];
        }

        foreach ($sections as $sectionIndex => $sectionData) {
            $section = FormSection::create([
                'form_id' => $form->id,
                'title_ar' => $sectionData['title_ar'] ?? 'قسم بدون عنوان',
                'title_en' => $sectionData['title_en'] ?? null,
                'description_ar' => $sectionData['description_ar'] ?? null,
                'description_en' => $sectionData['description_en'] ?? null,
                'sort_order' => $sectionData['order'] ?? ($sectionIndex + 1),
                'is_collapsed' => $sectionData['collapsed'] ?? false,
            ]);

            foreach ($sectionData['fields'] ?? [] as $fieldIndex => $field) {
                $this->createField($form, $section, $field, $fieldIndex);
            }
        }
    }

    protected function createField(Form $form, FormSection $section, array $field, int $index): void
    {
        $schema = $field['schema'] ?? $this->buildSchemaFromLegacyField($field);

        FormInput::create([
            'form_id' => $form->id,
            'section_id' => $section->id,
            'sort_order' => $field['order'] ?? ($index + 1),
            'name' => $field['name'] ?? $field['name_ar'] ?? $schema['label_ar'] ?? 'حقل',
            'label_en' => $field['label_en'] ?? $field['name_en'] ?? $schema['label_en'] ?? null,
            'type' => $field['type'],
            'required' => $field['required'] ?? ($schema['validation']['required'] ?? false),
            'options' => ! empty($field['options']) ? $field['options'] : null,
            'schema' => $schema,
        ]);
    }

    protected function buildSchemaFromLegacyField(array $field): array
    {
        return [
            'label_ar' => $field['name'] ?? $field['name_ar'] ?? '',
            'label_en' => $field['label_en'] ?? $field['name_en'] ?? '',
            'placeholder_ar' => $field['placeholder_ar'] ?? $field['placeholder'] ?? '',
            'placeholder_en' => $field['placeholder_en'] ?? '',
            'help_ar' => $field['help_ar'] ?? '',
            'help_en' => $field['help_en'] ?? '',
            'validation' => $field['validation'] ?? [
                'required' => (bool) ($field['required'] ?? false),
            ],
            'default_value' => $field['default_value'] ?? null,
            'default_mode' => $field['default_mode'] ?? 'static',
            'visibility' => $field['visibility'] ?? ['mode' => 'visible'],
        ];
    }

    public function toBuilderPayload(Form $form): array
    {
        $form->load(['sections.inputs']);

        if ($form->sections->isEmpty()) {
            return [
                'name' => $form->name,
                'name_en' => $form->name_en,
                'sections' => [[
                    'id' => 'sec_legacy',
                    'title_ar' => 'الحقول العامة',
                    'title_en' => 'General Fields',
                    'order' => 1,
                    'collapsed' => false,
                    'fields' => $form->inputs->map(fn ($input) => $this->fieldToArray($input))->values()->all(),
                ]],
                'publication_status' => $form->publication_status ?? 'draft',
                'visibility_settings' => $form->visibility_settings,
                'submission_settings' => $form->submission_settings,
                'workflow_definition' => $form->workflow_definition,
                'logic_rules' => $form->logic_rules ?? [],
            ];
        }

        return [
            'name' => $form->name,
            'name_en' => $form->name_en,
            'description_ar' => $form->description_ar,
            'description_en' => $form->description_en,
            'publication_status' => $form->publication_status ?? 'draft',
            'visibility_settings' => $form->visibility_settings,
            'submission_settings' => $form->submission_settings,
            'workflow_definition' => $form->workflow_definition,
            'logic_rules' => $form->logic_rules ?? [],
            'template_key' => $form->template_key,
            'sections' => $form->sections->map(function ($section) {
                return [
                    'id' => 'sec_'.$section->id,
                    'title_ar' => $section->title_ar,
                    'title_en' => $section->title_en,
                    'description_ar' => $section->description_ar,
                    'description_en' => $section->description_en,
                    'order' => $section->sort_order,
                    'collapsed' => $section->is_collapsed,
                    'fields' => $section->inputs->map(fn ($input) => $this->fieldToArray($input))->values()->all(),
                ];
            })->values()->all(),
        ];
    }

    protected function fieldToArray(FormInput $input): array
    {
        $schema = $input->schema ?? [];

        return [
            'id' => 'fld_'.$input->id,
            'name' => $input->name,
            'name_ar' => $schema['label_ar'] ?? $input->name,
            'name_en' => $input->label_en,
            'label_en' => $input->label_en,
            'type' => $input->type,
            'required' => $input->required,
            'options' => $input->options ?? [],
            'order' => $input->sort_order,
            'schema' => $schema,
        ];
    }
}
