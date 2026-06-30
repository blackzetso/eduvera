<?php

namespace Tests\Unit\FormBuilder;

use App\Models\Form;
use App\Models\FormInput;
use App\Models\FormSection;
use App\Services\FormBuilder\Runtime\FormRenderService;
use App\Support\FormBuilder\FormRenderException;
use App\Support\FormBuilder\FormRuntimeContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FormRenderServiceTest extends TestCase
{
    protected FormRenderService $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureFormBuilderTables();
        $this->truncateFormBuilderTables();

        $this->renderer = new FormRenderService;
    }

    protected function ensureFormBuilderTables(): void
    {
        if (! Schema::hasTable('forms')) {
            Schema::create('forms', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('name_en')->nullable();
                $table->text('description_ar')->nullable();
                $table->text('description_en')->nullable();
                $table->string('status')->default('enable');
                $table->string('publication_status')->default('draft');
                $table->unsignedSmallInteger('version')->default(2);
                $table->string('template_key')->nullable();
                $table->json('visibility_settings')->nullable();
                $table->json('submission_settings')->nullable();
                $table->json('workflow_definition')->nullable();
                $table->json('logic_rules')->nullable();
                $table->json('builder_settings')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('form_sections')) {
            Schema::create('form_sections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('form_id');
                $table->string('title_ar');
                $table->string('title_en')->nullable();
                $table->text('description_ar')->nullable();
                $table->text('description_en')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_collapsed')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('form_inputs')) {
            Schema::create('form_inputs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('form_id');
                $table->unsignedBigInteger('section_id')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('name');
                $table->string('label_en')->nullable();
                $table->string('type');
                $table->boolean('required')->default(false);
                $table->json('options')->nullable();
                $table->json('schema')->nullable();
                $table->timestamps();
            });
        }
    }

    protected function truncateFormBuilderTables(): void
    {
        FormInput::query()->delete();
        FormSection::query()->delete();
        Form::query()->delete();
    }

    public function test_renders_published_form_with_stable_runtime_keys(): void
    {
        $form = $this->createPublishedForm();
        $section = FormSection::create([
            'form_id' => $form->id,
            'title_ar' => 'بيانات التواصل',
            'title_en' => 'Contact Details',
            'description_ar' => 'أدخل بياناتك',
            'description_en' => 'Enter your details',
            'sort_order' => 1,
            'is_collapsed' => false,
        ]);

        $input = FormInput::create([
            'form_id' => $form->id,
            'section_id' => $section->id,
            'sort_order' => 1,
            'name' => 'الاسم',
            'label_en' => 'Name',
            'type' => 'text',
            'required' => true,
            'schema' => [
                'label_ar' => 'الاسم',
                'label_en' => 'Name',
                'placeholder_ar' => 'اكتب الاسم',
                'placeholder_en' => 'Enter name',
                'validation' => ['required' => true, 'min_length' => 2],
                'visibility' => ['mode' => 'visible'],
            ],
        ]);

        $payload = $this->renderer->render($form, FormRuntimeContext::anonymous('ar'));
        $array = $payload->toArray();

        $this->assertSame('sec_'.$section->id, $array['sections'][0]['id']);
        $this->assertSame('fld_'.$input->id, $array['sections'][0]['fields'][0]['key']);
        $this->assertSame('text', $array['sections'][0]['fields'][0]['type']);
        $this->assertTrue($array['sections'][0]['fields'][0]['required']);
        $this->assertSame(2, $array['sections'][0]['fields'][0]['validation']['min_length']);
        $this->assertNotEmpty($array['form']['snapshot_hash']);
    }

    public function test_resolves_arabic_and_english_localization(): void
    {
        $form = $this->createPublishedForm([
            'name' => 'نموذج زائر',
            'name_en' => 'Visitor Form',
            'description_ar' => 'وصف عربي',
            'description_en' => 'English description',
        ]);

        $section = FormSection::create([
            'form_id' => $form->id,
            'title_ar' => 'قسم',
            'title_en' => 'Section',
            'sort_order' => 1,
        ]);

        FormInput::create([
            'form_id' => $form->id,
            'section_id' => $section->id,
            'sort_order' => 1,
            'name' => 'الاسم',
            'label_en' => 'Name',
            'type' => 'text',
            'schema' => [
                'label_ar' => 'الاسم',
                'label_en' => 'Name',
            ],
        ]);

        $ar = $this->renderer->render($form, FormRuntimeContext::anonymous('ar'))->toArray();
        $en = $this->renderer->render($form, FormRuntimeContext::anonymous('en'))->toArray();

        $this->assertSame('نموذج زائر', $ar['form']['name']);
        $this->assertSame('وصف عربي', $ar['form']['description']);
        $this->assertSame('الاسم', $ar['sections'][0]['fields'][0]['label']);

        $this->assertSame('Visitor Form', $en['form']['name']);
        $this->assertSame('English description', $en['form']['description']);
        $this->assertSame('Name', $en['sections'][0]['fields'][0]['label']);
    }

    public function test_resolves_choice_field_options_for_active_locale(): void
    {
        $form = $this->createPublishedForm();
        $section = FormSection::create([
            'form_id' => $form->id,
            'title_ar' => 'اختيارات',
            'sort_order' => 1,
        ]);

        FormInput::create([
            'form_id' => $form->id,
            'section_id' => $section->id,
            'sort_order' => 1,
            'name' => 'النوع',
            'type' => 'select',
            'options' => [
                ['value' => 'teacher', 'label_ar' => 'معلم', 'label_en' => 'Teacher'],
                ['value' => 'admin', 'label_ar' => 'إداري', 'label_en' => 'Admin'],
            ],
            'schema' => ['label_ar' => 'النوع'],
        ]);

        $ar = $this->renderer->render($form, FormRuntimeContext::anonymous('ar'))->toArray();
        $en = $this->renderer->render($form, FormRuntimeContext::anonymous('en'))->toArray();

        $this->assertSame('معلم', $ar['sections'][0]['fields'][0]['resolved_options'][0]['label']);
        $this->assertSame('Teacher', $en['sections'][0]['fields'][0]['resolved_options'][0]['label']);
    }

    public function test_education_fields_emit_empty_resolved_options(): void
    {
        $form = $this->createPublishedForm();
        $section = FormSection::create([
            'form_id' => $form->id,
            'title_ar' => 'تعليمي',
            'sort_order' => 1,
        ]);

        FormInput::create([
            'form_id' => $form->id,
            'section_id' => $section->id,
            'sort_order' => 1,
            'name' => 'الصف',
            'type' => 'grade',
            'schema' => ['label_ar' => 'الصف'],
        ]);

        $payload = $this->renderer->render($form, FormRuntimeContext::anonymous('ar'))->toArray();

        $this->assertSame([], $payload['sections'][0]['fields'][0]['resolved_options']);
    }

    public function test_passes_through_logic_rules_and_settings(): void
    {
        $form = $this->createPublishedForm([
            'visibility_settings' => ['mode' => 'public', 'audiences' => []],
            'submission_settings' => ['limit' => 'once_per_user', 'date_from' => null, 'date_to' => null],
            'workflow_definition' => ['enabled' => true, 'stages' => [['id' => 'stage_1']]],
            'logic_rules' => [[
                'id' => 'rule_1',
                'field_key' => 'fld_1',
                'operator' => 'equals',
                'value' => 'teacher',
                'action' => 'show',
                'target_field_key' => 'fld_2',
            ]],
        ]);

        FormSection::create([
            'form_id' => $form->id,
            'title_ar' => 'قسم',
            'sort_order' => 1,
        ]);

        $payload = $this->renderer->render($form, FormRuntimeContext::anonymous('ar'))->toArray();

        $this->assertSame('public', $payload['settings']['visibility']['mode']);
        $this->assertSame('once_per_user', $payload['settings']['submission']['limit']);
        $this->assertTrue($payload['settings']['workflow']['enabled']);
        $this->assertSame('show', $payload['logic_rules'][0]['action']);
        $this->assertTrue($payload['capabilities']['anonymous']);
        $this->assertFalse($payload['capabilities']['attachments']);
        $this->assertSame(
            config('form-builder.supported_runtime_field_types'),
            $payload['capabilities']['supported_field_types'],
        );
    }

    public function test_renders_legacy_form_without_sections(): void
    {
        $form = $this->createPublishedForm();

        FormInput::create([
            'form_id' => $form->id,
            'sort_order' => 1,
            'name' => 'ملاحظة',
            'type' => 'textarea',
            'schema' => ['label_ar' => 'ملاحظة'],
        ]);

        $payload = $this->renderer->render($form, FormRuntimeContext::anonymous('ar'))->toArray();

        $this->assertSame('sec_legacy', $payload['sections'][0]['id']);
        $this->assertCount(1, $payload['sections'][0]['fields']);
    }

    public function test_snapshot_hash_changes_when_field_schema_changes(): void
    {
        $form = $this->createPublishedForm();
        $section = FormSection::create([
            'form_id' => $form->id,
            'title_ar' => 'قسم',
            'sort_order' => 1,
        ]);

        $input = FormInput::create([
            'form_id' => $form->id,
            'section_id' => $section->id,
            'sort_order' => 1,
            'name' => 'الاسم',
            'type' => 'text',
            'schema' => ['label_ar' => 'الاسم', 'validation' => ['required' => false]],
        ]);

        $before = $this->renderer->computeSnapshotHash($form->fresh(['sections.inputs']));

        $input->update([
            'schema' => ['label_ar' => 'الاسم', 'validation' => ['required' => true]],
        ]);

        $after = $this->renderer->computeSnapshotHash($form->fresh(['sections.inputs']));

        $this->assertNotSame($before, $after);
    }

    public function test_enforce_access_blocks_disabled_and_unpublished_forms(): void
    {
        $disabled = $this->createPublishedForm(['status' => 'disable']);
        $draft = $this->createPublishedForm(['publication_status' => 'draft']);

        $this->expectException(FormRenderException::class);
        $this->renderer->render($disabled, FormRuntimeContext::anonymous('ar'));
    }

    public function test_preview_context_bypasses_access_gates(): void
    {
        $draft = $this->createPublishedForm(['publication_status' => 'draft']);

        $section = FormSection::create([
            'form_id' => $draft->id,
            'title_ar' => 'قسم',
            'sort_order' => 1,
        ]);

        FormInput::create([
            'form_id' => $draft->id,
            'section_id' => $section->id,
            'sort_order' => 1,
            'name' => 'الاسم',
            'type' => 'text',
            'schema' => ['label_ar' => 'الاسم'],
        ]);

        $payload = $this->renderer->render($draft, FormRuntimeContext::preview('ar'));

        $this->assertSame('draft', $payload->toArray()['form']['publication_status']);
        $this->assertCount(1, $payload->toArray()['sections']);
    }

    public function test_runtime_payload_matches_contract_shape(): void
    {
        $form = $this->createPublishedForm();
        $section = FormSection::create([
            'form_id' => $form->id,
            'title_ar' => 'قسم',
            'sort_order' => 1,
        ]);

        FormInput::create([
            'form_id' => $form->id,
            'section_id' => $section->id,
            'sort_order' => 1,
            'name' => 'البريد',
            'type' => 'email',
            'schema' => [
                'label_ar' => 'البريد',
                'validation' => ['required' => true, 'email' => true],
            ],
        ]);

        $payload = $this->renderer->render($form, FormRuntimeContext::anonymous('ar'))->toArray();

        $this->assertArrayHasKey('form', $payload);
        $this->assertArrayHasKey('settings', $payload);
        $this->assertArrayHasKey('sections', $payload);
        $this->assertArrayHasKey('logic_rules', $payload);
        $this->assertArrayHasKey('capabilities', $payload);

        $this->assertArrayHasKey('snapshot_hash', $payload['form']);
        $this->assertArrayHasKey('visibility', $payload['settings']);
        $this->assertArrayHasKey('submission', $payload['settings']);
        $this->assertArrayHasKey('workflow', $payload['settings']);
        $this->assertArrayHasKey('resolved_options', $payload['sections'][0]['fields'][0]);
        $this->assertArrayHasKey('validation', $payload['sections'][0]['fields'][0]);
        $this->assertArrayHasKey('_i18n', $payload['sections'][0]['fields'][0]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createPublishedForm(array $overrides = []): Form
    {
        return Form::create(array_merge([
            'name' => 'نموذج',
            'name_en' => 'Form',
            'status' => 'enable',
            'publication_status' => 'published',
            'version' => 2,
        ], $overrides));
    }
}
