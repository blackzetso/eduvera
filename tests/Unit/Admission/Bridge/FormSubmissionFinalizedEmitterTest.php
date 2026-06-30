<?php

namespace Tests\Unit\Admission\Bridge;

use App\Events\FormBuilder\FormSubmissionFinalized;
use App\Jobs\Admission\Bridge\ProcessAdmissionBridgeSubmissionJob;
use App\Models\Form;
use App\Models\FormInput;
use App\Models\FormSection;
use App\Models\FormSubmission;
use App\Services\FormBuilder\Runtime\FormLogicEvaluator;
use App\Services\FormBuilder\Runtime\FormRenderService;
use App\Services\FormBuilder\Runtime\FormSubmissionService;
use App\Services\FormBuilder\Runtime\FormValidationService;
use App\Support\FormBuilder\FormRuntimeContext;
use App\Support\FormBuilder\FormSubmissionRequest;
use App\Support\FormBuilder\FormSubmissionStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FormSubmissionFinalizedEmitterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureFormBuilderTables();
        $this->truncateFormBuilderTables();
    }

    public function test_submit_dispatches_finalized_event_after_commit(): void
    {
        Event::fake([FormSubmissionFinalized::class]);

        [$form, $input] = $this->seedContactForm();
        $renderer = new FormRenderService;
        $runtime = $renderer->render($form, FormRuntimeContext::preview('ar'));
        $service = new FormSubmissionService(new FormLogicEvaluator, new FormValidationService);

        $service->submit(
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

        Event::assertDispatched(FormSubmissionFinalized::class, function (FormSubmissionFinalized $event) use ($form) {
            return $event->payload->formId === $form->id
                && $event->payload->submissionId > 0
                && $event->payload->toArray()['event'] === 'form_submission.finalized';
        });
    }

    public function test_listener_queues_bridge_job_when_enabled(): void
    {
        Queue::fake();
        Config::set('admissions_bridge.enabled', true);
        Config::set('admissions_bridge.processing_mode', 'queue');

        $submission = new FormSubmission([
            'id' => 10,
            'form_id' => 5,
            'status' => FormSubmissionStatus::SUBMITTED,
            'locale' => 'ar',
            'data' => ['fld_1' => 'Sara', '_meta' => ['snapshot' => ['form_version' => 2]]],
        ]);

        event(new FormSubmissionFinalized(
            \App\Support\FormBuilder\FormSubmissionFinalizedPayload::fromSubmission(
                $submission,
                FormRuntimeContext::anonymous('ar'),
            )
        ));

        Queue::assertPushed(ProcessAdmissionBridgeSubmissionJob::class);
    }

    /**
     * @return array{0: Form, 1: FormInput}
     */
    protected function seedContactForm(bool $required = true): array
    {
        $form = Form::create([
            'name' => 'Contact',
            'status' => 'enable',
            'publication_status' => 'published',
            'version' => 2,
            'submission_settings' => [
                'limit' => 'unlimited',
                'allow_draft' => false,
            ],
            'workflow_definition' => ['enabled' => false, 'stages' => []],
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
                $table->unsignedInteger('sort_order')->default(1);
                $table->boolean('is_collapsed')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('form_inputs')) {
            Schema::create('form_inputs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('form_id');
                $table->unsignedBigInteger('section_id');
                $table->unsignedInteger('sort_order')->default(1);
                $table->string('name')->nullable();
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
        foreach (['form_submissions', 'form_inputs', 'form_sections', 'forms'] as $table) {
            if (Schema::hasTable($table)) {
                \DB::table($table)->delete();
            }
        }
    }
}
