<?php

namespace Tests\Unit\FormBuilder;

use App\Models\Form;
use App\Models\FormInput;
use App\Models\FormSection;
use App\Models\FormSubmission;
use App\Services\FormBuilder\Runtime\FormRenderService;
use App\Services\FormBuilder\Runtime\FormSubmissionService;
use App\Support\FormBuilder\FormRuntimeContext;
use App\Support\FormBuilder\FormSubmissionException;
use App\Support\FormBuilder\FormSubmissionRequest;
use App\Support\FormBuilder\FormSubmissionSnapshot;
use App\Support\FormBuilder\FormSubmissionStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FormSubmissionServiceTest extends TestCase
{
    protected FormSubmissionService $submissions;

    protected FormRenderService $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('admissions_bridge.enabled', false);

        $this->ensureFormBuilderTables();
        $this->truncateFormBuilderTables();

        $this->renderer = new FormRenderService;
        $this->submissions = new FormSubmissionService(
            new \App\Services\FormBuilder\Runtime\FormLogicEvaluator,
            new \App\Services\FormBuilder\Runtime\FormValidationService,
        );
    }

    public function test_submits_valid_payload_with_snapshot_and_timeline(): void
    {
        [$form, $input] = $this->seedContactForm();
        $runtime = $this->renderer->render($form, FormRuntimeContext::preview('ar'));

        $result = $this->submissions->submit(
            $form,
            $runtime,
            new FormSubmissionRequest(
                data: ['fld_'.$input->id => 'Sara'],
                locale: 'ar',
                targetStatus: FormSubmissionStatus::SUBMITTED,
                snapshotHash: $runtime->snapshotHash(),
            ),
            FormRuntimeContext::anonymous('ar', '127.0.0.1'),
        );

        $this->assertTrue($result->isValid());
        $this->assertSame(FormSubmissionStatus::SUBMITTED, $result->submission->status);
        $this->assertSame('Sara', $result->submission->data['fld_'.$input->id]);
        $this->assertSame($runtime->snapshotHash(), FormSubmissionSnapshot::read($result->submission->data)['snapshot_hash']);
        $this->assertSame('created', $result->submission->timeline[0]['event']);
        $this->assertSame('submitted', $result->submission->timeline[1]['event']);
    }

    public function test_validation_failure_throws_with_result(): void
    {
        [$form, $input] = $this->seedContactForm(required: true);
        $runtime = $this->renderer->render($form, FormRuntimeContext::preview('ar'));

        try {
            $this->submissions->submit(
                $form,
                $runtime,
                new FormSubmissionRequest(data: [], snapshotHash: $runtime->snapshotHash()),
                FormRuntimeContext::anonymous('ar'),
            );
            $this->fail('Expected FormSubmissionException was not thrown.');
        } catch (FormSubmissionException $exception) {
            $this->assertNotNull($exception->validationResult());
            $this->assertFalse($exception->validationResult()->valid);
            $this->assertSame('required', $exception->validationResult()->errors[0]->rule);
        }
    }

    public function test_logic_required_field_is_enforced_on_submit(): void
    {
        $form = $this->createPublishedForm([
            'logic_rules' => [[
                'id' => 'rule_1',
                'field_key' => 'fld_1',
                'operator' => 'equals',
                'value' => 'yes',
                'action' => 'require',
                'target_field_key' => 'fld_2',
            ]],
        ]);

        $section = FormSection::create([
            'form_id' => $form->id,
            'title_ar' => 'قسم',
            'sort_order' => 1,
        ]);

        $trigger = FormInput::create([
            'form_id' => $form->id,
            'section_id' => $section->id,
            'sort_order' => 1,
            'name' => 'تفعيل',
            'type' => 'text',
            'schema' => ['label_ar' => 'تفعيل', 'validation' => ['required' => false]],
        ]);

        $target = FormInput::create([
            'form_id' => $form->id,
            'section_id' => $section->id,
            'sort_order' => 2,
            'name' => 'تفاصيل',
            'type' => 'text',
            'schema' => ['label_ar' => 'تفاصيل', 'validation' => ['required' => false]],
        ]);

        $runtime = $this->renderer->render($form->fresh(['sections.inputs']), FormRuntimeContext::preview('ar'));
        $runtime->logicRules[0]['field_key'] = 'fld_'.$trigger->id;
        $runtime->logicRules[0]['target_field_key'] = 'fld_'.$target->id;

        $this->expectException(FormSubmissionException::class);

        $this->submissions->submit(
            $form,
            $runtime,
            new FormSubmissionRequest(
                data: ['fld_'.$trigger->id => 'yes'],
                snapshotHash: $runtime->snapshotHash(),
            ),
            FormRuntimeContext::anonymous('ar'),
        );
    }

    public function test_snapshot_mismatch_is_rejected(): void
    {
        [$form] = $this->seedContactForm();
        $runtime = $this->renderer->render($form, FormRuntimeContext::preview('ar'));

        $this->expectException(FormSubmissionException::class);
        $this->expectExceptionMessage('Form definition changed');

        $this->submissions->submit(
            $form,
            $runtime,
            new FormSubmissionRequest(data: [], snapshotHash: 'stale-hash'),
            FormRuntimeContext::anonymous('ar'),
        );
    }

    public function test_draft_skips_required_validation_and_persists(): void
    {
        [$form, $input] = $this->seedContactForm(required: true, allowDraft: true);
        $runtime = $this->renderer->render($form, FormRuntimeContext::preview('ar'));

        $result = $this->submissions->submit(
            $form,
            $runtime,
            new FormSubmissionRequest(
                data: [],
                targetStatus: FormSubmissionStatus::DRAFT,
                snapshotHash: $runtime->snapshotHash(),
            ),
            FormRuntimeContext::anonymous('ar'),
        );

        $this->assertSame(FormSubmissionStatus::DRAFT, $result->submission->status);
        $this->assertSame('created', $result->submission->timeline[0]['event']);
        $this->assertArrayNotHasKey('fld_'.$input->id, $result->submission->data);
    }

    public function test_workflow_enabled_sets_under_review_and_stage(): void
    {
        [$form, $input] = $this->seedContactForm(workflowEnabled: true);
        $runtime = $this->renderer->render($form, FormRuntimeContext::preview('ar'));

        $result = $this->submissions->submit(
            $form,
            $runtime,
            new FormSubmissionRequest(
                data: ['fld_'.$input->id => 'Sara'],
                snapshotHash: $runtime->snapshotHash(),
            ),
            FormRuntimeContext::anonymous('ar'),
        );

        $this->assertSame(FormSubmissionStatus::UNDER_REVIEW, $result->submission->status);
        $this->assertSame('stage_1', $result->submission->workflow_stage);
    }

    public function test_draft_can_be_updated_and_finalized(): void
    {
        [$form, $input] = $this->seedContactForm(required: true, allowDraft: true);
        $runtime = $this->renderer->render($form, FormRuntimeContext::preview('ar'));

        $draft = $this->submissions->submit(
            $form,
            $runtime,
            new FormSubmissionRequest(
                data: [],
                targetStatus: FormSubmissionStatus::DRAFT,
                snapshotHash: $runtime->snapshotHash(),
            ),
            FormRuntimeContext::anonymous('ar'),
        );

        $final = $this->submissions->submit(
            $form,
            $runtime,
            new FormSubmissionRequest(
                data: ['fld_'.$input->id => 'Sara'],
                targetStatus: FormSubmissionStatus::SUBMITTED,
                snapshotHash: $runtime->snapshotHash(),
                submissionId: $draft->submission->id,
            ),
            FormRuntimeContext::anonymous('ar'),
        );

        $this->assertSame($draft->submission->id, $final->submission->id);
        $this->assertSame(FormSubmissionStatus::SUBMITTED, $final->submission->status);
        $this->assertSame('submitted', $final->submission->timeline[1]['event']);
    }

    public function test_transition_status_appends_timeline_entry(): void
    {
        [$form, $input] = $this->seedContactForm();
        $runtime = $this->renderer->render($form, FormRuntimeContext::preview('ar'));

        $result = $this->submissions->submit(
            $form,
            $runtime,
            new FormSubmissionRequest(
                data: ['fld_'.$input->id => 'Sara'],
                snapshotHash: $runtime->snapshotHash(),
            ),
            FormRuntimeContext::authenticated(9, 'staff', 'ar'),
        );

        $updated = $this->submissions->transitionStatus(
            $result->submission,
            FormSubmissionStatus::APPROVED,
            FormRuntimeContext::authenticated(9, 'staff', 'ar'),
            'Approved by staff',
        );

        $this->assertSame(FormSubmissionStatus::APPROVED, $updated->status);
        $this->assertSame('status_changed', $updated->timeline[2]['event']);
        $this->assertSame('Approved by staff', $updated->timeline[2]['comment']);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        [$form, $input] = $this->seedContactForm();
        $runtime = $this->renderer->render($form, FormRuntimeContext::preview('ar'));

        $result = $this->submissions->submit(
            $form,
            $runtime,
            new FormSubmissionRequest(
                data: ['fld_'.$input->id => 'Sara'],
                snapshotHash: $runtime->snapshotHash(),
            ),
            FormRuntimeContext::anonymous('ar'),
        );

        $this->expectException(FormSubmissionException::class);
        $this->expectExceptionMessage('Cannot transition');

        $this->submissions->transitionStatus(
            $result->submission,
            FormSubmissionStatus::DRAFT,
            FormRuntimeContext::anonymous('ar'),
        );
    }

    public function test_once_per_user_submission_limit_is_enforced(): void
    {
        [$form, $input] = $this->seedContactForm(limit: 'once_per_user');
        $runtime = $this->renderer->render($form, FormRuntimeContext::preview('ar'));
        $context = FormRuntimeContext::authenticated(15, 'student', 'ar');

        $this->submissions->submit(
            $form,
            $runtime,
            new FormSubmissionRequest(
                data: ['fld_'.$input->id => 'Sara'],
                snapshotHash: $runtime->snapshotHash(),
            ),
            $context,
        );

        $this->expectException(FormSubmissionException::class);
        $this->expectExceptionMessage('already submitted');

        $this->submissions->submit(
            $form,
            $runtime,
            new FormSubmissionRequest(
                data: ['fld_'.$input->id => 'Sara 2'],
                snapshotHash: $runtime->snapshotHash(),
            ),
            $context,
        );
    }

    public function test_unsupported_field_types_are_not_persisted(): void
    {
        $form = $this->createPublishedForm();
        $section = FormSection::create([
            'form_id' => $form->id,
            'title_ar' => 'بيانات',
            'sort_order' => 1,
        ]);

        $text = FormInput::create([
            'form_id' => $form->id,
            'section_id' => $section->id,
            'sort_order' => 1,
            'name' => 'الاسم',
            'type' => 'text',
            'schema' => ['label_ar' => 'الاسم'],
        ]);

        $grade = FormInput::create([
            'form_id' => $form->id,
            'section_id' => $section->id,
            'sort_order' => 2,
            'name' => 'الصف',
            'type' => 'grade',
            'required' => true,
            'schema' => ['label_ar' => 'الصف', 'validation' => ['required' => true]],
        ]);

        $form = $form->fresh(['sections.inputs']);
        $runtime = $this->renderer->render($form, FormRuntimeContext::preview('ar'));

        $result = $this->submissions->submit(
            $form,
            $runtime,
            new FormSubmissionRequest(
                data: [
                    'fld_'.$text->id => 'Sara',
                    'fld_'.$grade->id => 'Grade 5',
                ],
                snapshotHash: $runtime->snapshotHash(),
            ),
            FormRuntimeContext::anonymous('ar'),
        );

        $this->assertSame('Sara', $result->submission->data['fld_'.$text->id]);
        $this->assertArrayNotHasKey('fld_'.$grade->id, $result->submission->data);
    }

    public function test_unknown_field_keys_are_stripped_from_persisted_data(): void
    {
        [$form, $input] = $this->seedContactForm();
        $runtime = $this->renderer->render($form, FormRuntimeContext::preview('ar'));

        $result = $this->submissions->submit(
            $form,
            $runtime,
            new FormSubmissionRequest(
                data: [
                    'fld_'.$input->id => 'Sara',
                    'fld_unknown' => 'hack',
                ],
                snapshotHash: $runtime->snapshotHash(),
            ),
            FormRuntimeContext::anonymous('ar'),
        );

        $this->assertArrayHasKey('fld_'.$input->id, $result->submission->data);
        $this->assertArrayNotHasKey('fld_unknown', $result->submission->data);
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

        if (! Schema::hasTable('form_submissions')) {
            Schema::create('form_submissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('form_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('status', 30)->default('submitted');
                $table->string('workflow_stage')->nullable();
                $table->json('data');
                $table->json('timeline')->nullable();
                $table->string('locale', 5)->default('ar');
                $table->string('ip_address')->nullable();
                $table->timestamps();
            });
        }
    }

    protected function truncateFormBuilderTables(): void
    {
        FormSubmission::query()->delete();
        FormInput::query()->delete();
        FormSection::query()->delete();
        Form::query()->delete();
    }

    /**
     * @return array{0: Form, 1: FormInput}
     */
    protected function seedContactForm(
        bool $required = true,
        bool $allowDraft = false,
        bool $workflowEnabled = false,
        string $limit = 'unlimited',
    ): array {
        $form = $this->createPublishedForm([
            'submission_settings' => [
                'limit' => $limit,
                'date_from' => null,
                'date_to' => null,
                'allow_draft' => $allowDraft,
            ],
            'workflow_definition' => [
                'enabled' => $workflowEnabled,
                'stages' => [['id' => 'stage_1', 'name_ar' => 'مراجعة']],
            ],
        ]);

        $section = FormSection::create([
            'form_id' => $form->id,
            'title_ar' => 'بيانات',
            'sort_order' => 1,
        ]);

        $input = FormInput::create([
            'form_id' => $form->id,
            'section_id' => $section->id,
            'sort_order' => 1,
            'name' => 'الاسم',
            'type' => 'text',
            'required' => $required,
            'schema' => [
                'label_ar' => 'الاسم',
                'validation' => ['required' => $required],
            ],
        ]);

        return [$form->fresh(['sections.inputs']), $input];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createPublishedForm(array $overrides = []): Form
    {
        return Form::create(array_merge([
            'name' => 'نموذج',
            'status' => 'enable',
            'publication_status' => 'published',
            'version' => 2,
        ], $overrides));
    }
}
