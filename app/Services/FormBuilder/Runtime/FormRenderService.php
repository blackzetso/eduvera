<?php

namespace App\Services\FormBuilder\Runtime;

use App\Models\Form;
use App\Models\FormInput;
use App\Models\FormSection;
use App\Support\FormBuilder\FormFieldDefinition;
use App\Support\FormBuilder\FormRenderException;
use App\Support\FormBuilder\FormRuntimeFieldTypes;
use App\Support\FormBuilder\FormRuntimeContext;
use App\Support\FormBuilder\FormRuntimePayload;
use Illuminate\Support\Collection;

class FormRenderService
{
    /**
     * @var array<int, string>
     */
    protected array $optionFieldTypes;

    public function __construct()
    {
        $this->optionFieldTypes = config('form-builder.option_field_types', []);
    }

    public function render(Form $form, FormRuntimeContext $context): FormRuntimePayload
    {
        $this->assertRenderable($form, $context);

        $form->load(['sections.inputs', 'inputs']);
        $locale = $context->resolvedLocale();

        return new FormRuntimePayload(
            form: $this->buildFormMeta($form, $locale),
            settings: $this->buildSettings($form),
            sections: $this->buildSections($form, $locale),
            logicRules: $this->normalizeLogicRules($form->logic_rules ?? []),
            capabilities: $this->buildCapabilities($form),
        );
    }

    public function computeSnapshotHash(Form $form): string
    {
        $form->load(['sections.inputs', 'inputs']);

        $canonical = [
            'id' => $form->id,
            'updated_at' => $form->updated_at?->toIso8601String(),
            'version' => $form->version,
            'structure' => $this->canonicalStructure($form),
        ];

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE));
    }

    protected function assertRenderable(Form $form, FormRuntimeContext $context): void
    {
        if (! $context->enforceAccess) {
            return;
        }

        if ($form->status !== 'enable') {
            throw FormRenderException::notRenderable('Form is disabled.');
        }

        if (($form->publication_status ?? 'draft') !== 'published') {
            throw FormRenderException::notRenderable('Form is not published.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildFormMeta(Form $form, string $locale): array
    {
        return [
            'id' => $form->id,
            'version' => (int) ($form->version ?? config('form-builder.version', 2)),
            'snapshot_hash' => $this->computeSnapshotHash($form),
            'name' => $this->localized($form->name, $form->name_en, $locale),
            'description' => $this->localizedNullable(
                $form->description_ar,
                $form->description_en,
                $locale,
            ),
            'locale' => $locale,
            'status' => $form->status,
            'publication_status' => $form->publication_status ?? 'draft',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSettings(Form $form): array
    {
        $visibility = $form->visibility_settings ?? [
            'mode' => 'staff',
            'audiences' => ['staff'],
        ];

        $submission = $form->submission_settings ?? [
            'limit' => 'unlimited',
            'date_from' => null,
            'date_to' => null,
        ];

        $workflow = $form->workflow_definition ?? [
            'enabled' => false,
            'stages' => [],
        ];

        return [
            'visibility' => [
                'mode' => $visibility['mode'] ?? 'staff',
                'audiences' => $visibility['audiences'] ?? [],
            ],
            'submission' => [
                'limit' => $submission['limit'] ?? 'unlimited',
                'date_from' => $submission['date_from'] ?? null,
                'date_to' => $submission['date_to'] ?? null,
                'allow_draft' => (bool) ($submission['allow_draft'] ?? false),
            ],
            'workflow' => [
                'enabled' => (bool) ($workflow['enabled'] ?? false),
                'stages' => $workflow['stages'] ?? [],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildSections(Form $form, string $locale): array
    {
        if ($form->sections->isEmpty()) {
            return [$this->buildLegacySection($form, $locale)];
        }

        return $form->sections
            ->sortBy('sort_order')
            ->values()
            ->map(fn (FormSection $section) => $this->buildSection($section, $locale))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildLegacySection(Form $form, string $locale): array
    {
        return [
            'id' => 'sec_legacy',
            'order' => 1,
            'title' => $locale === 'en' ? 'General Fields' : 'الحقول العامة',
            'description' => null,
            'collapsed' => false,
            'fields' => $form->inputs
                ->sortBy('sort_order')
                ->values()
                ->map(fn (FormInput $input) => $this->buildField($input, $locale))
                ->all(),
            '_i18n' => [
                'title' => [
                    'ar' => 'الحقول العامة',
                    'en' => 'General Fields',
                ],
                'description' => [
                    'ar' => null,
                    'en' => null,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSection(FormSection $section, string $locale): array
    {
        $sectionKey = $this->sectionKey($section);

        return [
            'id' => $sectionKey,
            'order' => (int) $section->sort_order,
            'title' => $this->localized($section->title_ar, $section->title_en, $locale),
            'description' => $this->localizedNullable(
                $section->description_ar,
                $section->description_en,
                $locale,
            ),
            'collapsed' => (bool) $section->is_collapsed,
            'fields' => $section->inputs
                ->sortBy('sort_order')
                ->values()
                ->map(fn (FormInput $input) => $this->buildField($input, $locale))
                ->all(),
            '_i18n' => [
                'title' => [
                    'ar' => $section->title_ar,
                    'en' => $section->title_en,
                ],
                'description' => [
                    'ar' => $section->description_ar,
                    'en' => $section->description_en,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildField(FormInput $input, string $locale): array
    {
        $schema = $input->schema ?? [];
        $validation = FormFieldDefinition::normalizeValidation($schema['validation'] ?? [
            'required' => (bool) $input->required,
        ]);

        $visibilityMode = $schema['visibility']['mode'] ?? 'visible';
        $options = $input->options ?? [];
        $resolvedOptions = $this->resolveOptions($input->type, $options, $locale);

        return [
            'key' => $this->fieldKey($input),
            'type' => $input->type,
            'order' => (int) $input->sort_order,
            'label' => $this->localized(
                (string) ($schema['label_ar'] ?? $input->name),
                $schema['label_en'] ?? $input->label_en,
                $locale,
            ),
            'placeholder' => $this->localized(
                (string) ($schema['placeholder_ar'] ?? ''),
                $schema['placeholder_en'] ?? null,
                $locale,
            ),
            'help' => $this->localized(
                (string) ($schema['help_ar'] ?? ''),
                $schema['help_en'] ?? null,
                $locale,
            ),
            'required' => (bool) ($validation['required'] ?? $input->required),
            'readonly' => $visibilityMode === 'readonly',
            'hidden' => $visibilityMode === 'hidden',
            'default_value' => $schema['default_value'] ?? null,
            'options' => $options,
            'resolved_options' => $resolvedOptions,
            'validation' => $validation,
            'constraints' => $this->buildConstraints($input->type),
            '_i18n' => [
                'label' => [
                    'ar' => (string) ($schema['label_ar'] ?? $input->name),
                    'en' => $schema['label_en'] ?? $input->label_en,
                ],
                'placeholder' => [
                    'ar' => (string) ($schema['placeholder_ar'] ?? ''),
                    'en' => $schema['placeholder_en'] ?? null,
                ],
                'help' => [
                    'ar' => (string) ($schema['help_ar'] ?? ''),
                    'en' => $schema['help_en'] ?? null,
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return array<int, array<string, mixed>>
     */
    protected function resolveOptions(string $type, array $options, string $locale): array
    {
        if (! in_array($type, $this->optionFieldTypes, true)) {
            return [];
        }

        return collect($options)
            ->map(function (array $option) use ($locale) {
                $labelAr = (string) ($option['label_ar'] ?? $option['value'] ?? '');
                $labelEn = $option['label_en'] ?? null;

                return [
                    'value' => (string) ($option['value'] ?? ''),
                    'label' => $this->localized($labelAr, $labelEn, $locale),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildConstraints(string $type): array
    {
        if (! in_array($type, ['file', 'image'], true)) {
            return [];
        }

        return [
            'accept' => [],
            'max_size_mb' => null,
        ];
    }

    /**
     * @return array<string, bool>
     */
    protected function buildCapabilities(Form $form): array
    {
        $visibility = $form->visibility_settings ?? [];
        $submission = $form->submission_settings ?? [];

        return [
            'anonymous' => ($visibility['mode'] ?? 'staff') === 'public',
            'draft' => (bool) ($submission['allow_draft'] ?? false),
            'attachments' => false,
            'file_fields_accepted' => false,
            'supported_field_types' => FormRuntimeFieldTypes::supported(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rules
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeLogicRules(array $rules): array
    {
        return collect($rules)
            ->map(function (array $rule) {
                return [
                    'id' => $rule['id'] ?? null,
                    'field_key' => (string) ($rule['field_key'] ?? ''),
                    'operator' => (string) ($rule['operator'] ?? 'equals'),
                    'value' => $rule['value'] ?? null,
                    'action' => (string) ($rule['action'] ?? 'show'),
                    'target_field_key' => $rule['target_field_key'] ?? null,
                    'target_section_id' => $rule['target_section_id'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function canonicalStructure(Form $form): array
    {
        if ($form->sections->isEmpty()) {
            return [
                'legacy' => true,
                'inputs' => $this->canonicalInputs($form->inputs),
            ];
        }

        return [
            'legacy' => false,
            'sections' => $form->sections
                ->sortBy('sort_order')
                ->values()
                ->map(fn (FormSection $section) => [
                    'id' => $section->id,
                    'sort_order' => $section->sort_order,
                    'inputs' => $this->canonicalInputs($section->inputs),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function canonicalInputs(Collection $inputs): array
    {
        return $inputs
            ->sortBy('sort_order')
            ->values()
            ->map(fn (FormInput $input) => [
                'id' => $input->id,
                'type' => $input->type,
                'sort_order' => $input->sort_order,
                'required' => (bool) $input->required,
                'options' => $input->options,
                'schema' => $input->schema,
            ])
            ->all();
    }

    protected function fieldKey(FormInput $input): string
    {
        return 'fld_'.$input->id;
    }

    protected function sectionKey(FormSection $section): string
    {
        return 'sec_'.$section->id;
    }

    protected function localized(string $arabic, ?string $english, string $locale): string
    {
        if ($locale === 'en') {
            return ($english !== null && $english !== '') ? $english : $arabic;
        }

        return $arabic !== '' ? $arabic : (string) $english;
    }

    protected function localizedNullable(?string $arabic, ?string $english, string $locale): ?string
    {
        $value = $this->localized((string) ($arabic ?? ''), $english, $locale);

        return $value === '' ? null : $value;
    }
}
