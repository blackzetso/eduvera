<?php

use App\Models\Website\WebsiteSetting;
use App\Services\Admission\AdmissionDocumentDefinitionService;
use App\Support\Website\WebsiteDefaultsRepository;
use App\Support\Website\WebsiteSettingKeys;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_document_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('label_ar');
            $table->string('label_en')->nullable();
            $table->boolean('required')->default(true);
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('source_type', 32)->default('settings');
            $table->string('source_ref', 120)->nullable();
            $table->timestamps();
        });

        $this->seedDefinitions();

        app(AdmissionDocumentDefinitionService::class)->syncWebsiteSettingCache();
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_document_definitions');
    }

    protected function seedDefinitions(): void
    {
        $configured = WebsiteSetting::getValue(
            WebsiteSettingKeys::ADMISSION_DOCUMENTS,
            WebsiteDefaultsRepository::builtinDefaults()['admissionDocuments'] ?? [],
        );

        $defaults = $this->defaultDefinitions();
        $rows = $this->normalizeConfiguredDocuments($configured);

        if ($rows === []) {
            $rows = $defaults;
        }

        $now = now();

        foreach ($rows as $row) {
            DB::table('admission_document_definitions')->updateOrInsert(
                ['key' => $row['key']],
                array_merge($row, ['created_at' => $now, 'updated_at' => $now]),
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function defaultDefinitions(): array
    {
        return [
            ['key' => 'birth-certificate', 'label_ar' => 'شهادة الميلاد', 'label_en' => 'Birth certificate (copy)', 'required' => true, 'enabled' => true, 'sort_order' => 10, 'source_type' => 'settings', 'source_ref' => null],
            ['key' => 'immunization-record', 'label_ar' => 'سجل التطعيمات', 'label_en' => 'Immunization record', 'required' => true, 'enabled' => true, 'sort_order' => 20, 'source_type' => 'settings', 'source_ref' => null],
            ['key' => 'passport-photographs', 'label_ar' => 'الصور الشخصية', 'label_en' => 'Passport-size photographs', 'required' => true, 'enabled' => true, 'sort_order' => 30, 'source_type' => 'settings', 'source_ref' => null],
            ['key' => 'previous-school-reports', 'label_ar' => 'تقارير المدرسة السابقة', 'label_en' => 'Previous school reports (2 years)', 'required' => true, 'enabled' => true, 'sort_order' => 40, 'source_type' => 'settings', 'source_ref' => null],
            ['key' => 'passport-copy', 'label_ar' => 'صورة جواز السفر', 'label_en' => 'Passport copy', 'required' => false, 'enabled' => true, 'sort_order' => 50, 'source_type' => 'settings', 'source_ref' => null],
            ['key' => 'recommendation-letter', 'label_ar' => 'خطاب توصية', 'label_en' => 'Recommendation letter', 'required' => false, 'enabled' => true, 'sort_order' => 60, 'source_type' => 'settings', 'source_ref' => null],
        ];
    }

    /**
     * @param  array<int, mixed>  $configured
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeConfiguredDocuments(array $configured): array
    {
        $labelMap = [
            'birth certificate (copy)' => 'birth-certificate',
            'birth certificate' => 'birth-certificate',
            'immunization record' => 'immunization-record',
            'passport-size photographs' => 'passport-photographs',
            'previous school reports (2 years)' => 'previous-school-reports',
            'previous school reports' => 'previous-school-reports',
        ];

        $arabicMap = [
            'شهادة الميلاد' => 'birth-certificate',
            'سجل التطعيمات' => 'immunization-record',
            'الصور الشخصية' => 'passport-photographs',
            'تقارير المدرسة السابقة' => 'previous-school-reports',
            'صورة جواز السفر' => 'passport-copy',
            'خطاب توصية' => 'recommendation-letter',
        ];

        $rows = [];

        foreach ($configured as $index => $doc) {
            $label = is_array($doc) ? ($doc['label'] ?? '') : (string) $doc;
            if ($label === '') {
                continue;
            }

            $lower = Str::lower(trim($label));
            $key = $arabicMap[$label]
                ?? $labelMap[$lower]
                ?? Str::slug($label)
                ?: 'document-'.$index;

            $isArabic = (bool) preg_match('/[\x{0600}-\x{06FF}]/u', $label);

            $rows[] = [
                'key' => $key,
                'label_ar' => $isArabic ? $label : $this->arabicLabelForKey($key, $label),
                'label_en' => $isArabic ? null : $label,
                'required' => (bool) (is_array($doc) ? ($doc['required'] ?? true) : true),
                'enabled' => (bool) (is_array($doc) ? ($doc['enabled'] ?? true) : true),
                'sort_order' => (int) (is_array($doc) ? ($doc['sort_order'] ?? (($index + 1) * 10)) : (($index + 1) * 10)),
                'source_type' => is_array($doc) ? ($doc['source_type'] ?? 'settings') : 'settings',
                'source_ref' => is_array($doc) ? ($doc['source_ref'] ?? null) : null,
            ];
        }

        return $rows;
    }

    protected function arabicLabelForKey(string $key, string $fallback): string
    {
        foreach ($this->defaultDefinitions() as $def) {
            if ($def['key'] === $key) {
                return $def['label_ar'];
            }
        }

        return $fallback;
    }
};
