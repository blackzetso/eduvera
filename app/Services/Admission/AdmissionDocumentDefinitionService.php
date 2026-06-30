<?php

namespace App\Services\Admission;

use App\Models\Admission\AdmissionDocumentDefinition;
use App\Models\Website\WebsiteSetting;
use App\Support\Website\WebsiteSettingKeys;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AdmissionDocumentDefinitionService
{
    /**
     * @return Collection<int, AdmissionDocumentDefinition>
     */
    public function activeDefinitions(): Collection
    {
        return AdmissionDocumentDefinition::query()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, AdmissionDocumentDefinition>
     */
    public function allDefinitions(): Collection
    {
        return AdmissionDocumentDefinition::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forAdminSettings(): array
    {
        return $this->allDefinitions()
            ->map(fn (AdmissionDocumentDefinition $def) => [
                'id' => $def->id,
                'key' => $def->key,
                'label_ar' => $def->label_ar,
                'label_en' => $def->label_en,
                'required' => $def->required,
                'enabled' => $def->enabled,
                'sort_order' => $def->sort_order,
                'source_type' => $def->source_type,
                'source_ref' => $def->source_ref,
            ])
            ->values()
            ->all();
    }

    /**
     * Public website / Dova payload (enabled only).
     *
     * @return array<int, array{label: string, required: bool, key: string}>
     */
    public function forPublicDisplay(): array
    {
        return $this->activeDefinitions()
            ->map(fn (AdmissionDocumentDefinition $def) => [
                'key' => $def->key,
                'label' => $def->label_en ?: $def->label_ar,
                'label_ar' => $def->label_ar,
                'required' => $def->required,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function syncFromAdminInput(array $rows): void
    {
        $seenKeys = [];

        foreach ($rows as $index => $row) {
            $labelAr = trim((string) ($row['label_ar'] ?? ''));
            if ($labelAr === '') {
                continue;
            }

            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '') {
                $key = Str::slug($labelAr) ?: 'document-'.($index + 1);
            }

            $baseKey = $key;
            $suffix = 2;
            while (in_array($key, $seenKeys, true)) {
                $key = $baseKey.'-'.$suffix;
                $suffix++;
            }
            $seenKeys[] = $key;

            $attributes = [
                'label_ar' => $labelAr,
                'label_en' => filled($row['label_en'] ?? null) ? trim((string) $row['label_en']) : null,
                'required' => (bool) ($row['required'] ?? false),
                'enabled' => (bool) ($row['enabled'] ?? true),
                'sort_order' => (int) ($row['sort_order'] ?? (($index + 1) * 10)),
                'source_type' => AdmissionDocumentDefinition::SOURCE_SETTINGS,
                'source_ref' => null,
            ];

            if (! empty($row['id'])) {
                $definition = AdmissionDocumentDefinition::query()->find($row['id']);
                if ($definition) {
                    if ($definition->source_type === AdmissionDocumentDefinition::SOURCE_FORM_BUILDER) {
                        continue;
                    }

                    $definition->update(array_merge($attributes, ['key' => $definition->key]));

                    continue;
                }
            }

            AdmissionDocumentDefinition::query()->updateOrCreate(
                ['key' => $key],
                $attributes,
            );
        }

        $this->syncWebsiteSettingCache();
    }

    public function syncWebsiteSettingCache(): void
    {
        $payload = $this->forPublicDisplay();

        WebsiteSetting::putValue(WebsiteSettingKeys::ADMISSION_DOCUMENTS, array_map(
            fn (array $item) => [
                'key' => $item['key'],
                'label' => $item['label'],
                'label_ar' => $item['label_ar'],
                'required' => $item['required'],
            ],
            $payload,
        ));
    }

    /**
     * Future Form Builder hook — register a document requirement from an upload field.
     */
    public function registerFromFormBuilder(string $fieldKey, string $labelAr, bool $required, ?string $labelEn = null): AdmissionDocumentDefinition
    {
        $definition = AdmissionDocumentDefinition::query()->updateOrCreate(
            [
                'source_type' => AdmissionDocumentDefinition::SOURCE_FORM_BUILDER,
                'source_ref' => $fieldKey,
            ],
            [
                'key' => 'form-'.Str::slug($fieldKey),
                'label_ar' => $labelAr,
                'label_en' => $labelEn,
                'required' => $required,
                'enabled' => true,
                'sort_order' => (int) ((AdmissionDocumentDefinition::max('sort_order') ?? 0) + 10),
            ],
        );

        $this->syncWebsiteSettingCache();

        return $definition;
    }
}
