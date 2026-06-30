<?php

namespace Tests\Unit\Admission\Bridge;

use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionBridgeDeadLetter;
use App\Models\Admission\AdmissionBridgeRun;
use App\Models\Admission\AdmissionCaseSubmission;
use App\Models\Form;
use App\Services\Admission\AdmissionPipelineService;
use App\Services\Admission\AdmissionReferenceService;
use App\Services\Admission\Bridge\AdmissionBindingResolver;
use App\Services\Admission\Bridge\AdmissionBridgeConsumer;
use App\Services\Admission\Bridge\AdmissionBridgeDeadLetterRecorder;
use App\Services\Admission\Bridge\AdmissionBridgeRunRecorder;
use App\Services\Admission\Bridge\AdmissionMappingEngine;
use App\Services\Admission\Bridge\BridgeCampusVisitCaseOrchestrator;
use App\Services\Admission\Bridge\BridgeMappingTransformApplicator;
use App\Services\Admission\Bridge\BridgeMappingValidator;
use App\Support\Admission\Bridge\AdmissionBridgeConfig;
use App\Support\Admission\Bridge\AdmissionBridgeConsumerResult;
use App\Support\Admission\Bridge\BridgeErrorCode;
use App\Support\Admission\Bridge\BridgeFormVersionGuard;
use App\Support\Admission\Bridge\BridgeRunOutcome;
use App\Support\Admission\Bridge\BridgeRunStatus;
use App\Support\FormBuilder\FormSubmissionFinalizedPayload;
use App\Support\FormBuilder\FormSubmissionSnapshot;
use App\Support\FormBuilder\FormSubmissionStatus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\Support\AdmissionBridgeTestSchema;
use Tests\TestCase;

class BridgePhase3IntegrationTest extends TestCase
{
    use AdmissionBridgeTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureBridgeTestTables();
        $this->truncateBridgeTestTables();

        Config::set('admissions_bridge.enabled', true);
        Config::set('admissions_bridge.dlq_enabled', true);
    }

    public function test_submission_maps_to_admission_case_and_completes_bridge_run(): void
    {
        $form = $this->createPublishedForm();
        $this->setBinding($form->id, $this->fieldMap());
        $event = $this->makeEvent($form->id, submissionId: 4001);

        $result = $this->consumer()->consume($event);

        $this->assertSame(AdmissionBridgeConsumerResult::COMPLETED, $result->status);
        $this->assertDatabaseHas('admission_bridge_runs', [
            'submission_id' => 4001,
            'status' => BridgeRunStatus::COMPLETED,
            'outcome' => BridgeRunOutcome::CASE_CREATED,
        ]);
        $this->assertSame(1, AdmissionApplication::count());
        $this->assertSame(1, AdmissionCaseSubmission::count());
        $this->assertNotNull($result->run?->admission_case_id);
        $this->assertNotNull($result->run?->duration_ms);
    }

    public function test_submission_replay_is_idempotent(): void
    {
        $form = $this->createPublishedForm();
        $this->setBinding($form->id, $this->fieldMap());
        $event = $this->makeEvent($form->id, submissionId: 4002);

        $first = $this->consumer()->consume($event);
        $second = $this->consumer()->consume($event);

        $this->assertSame(AdmissionBridgeConsumerResult::COMPLETED, $first->status);
        $this->assertSame(AdmissionBridgeConsumerResult::RESUMED, $second->status);
        $this->assertSame(1, AdmissionBridgeRun::count());
        $this->assertSame(1, AdmissionApplication::count());
        $this->assertSame(1, AdmissionCaseSubmission::count());
    }

    public function test_missing_required_fields_fail_and_write_dlq(): void
    {
        $form = $this->createPublishedForm();
        $this->setBinding($form->id, $this->fieldMap());
        $event = $this->makeEvent($form->id, submissionId: 4003, fields: [
            'fld_2' => '01001234567',
            'fld_3' => 'parent@example.com',
        ], onlyProvidedFields: true);

        $result = $this->consumer()->consume($event);

        $this->assertSame(AdmissionBridgeConsumerResult::DEAD_LETTERED, $result->status);
        $this->assertDatabaseHas('admission_bridge_runs', [
            'submission_id' => 4003,
            'status' => BridgeRunStatus::FAILED,
            'error_code' => BridgeErrorCode::MAP_VALIDATION_FAILED,
        ]);
        $this->assertSame(1, AdmissionBridgeDeadLetter::count());
        $this->assertSame(0, AdmissionApplication::count());
    }

    public function test_mapping_failure_dlq_is_deduplicated_on_replay(): void
    {
        $form = $this->createPublishedForm();
        $this->setBinding($form->id, []);
        $event = $this->makeEvent($form->id, submissionId: 4004);

        $this->consumer()->consume($event);
        $this->consumer()->consume($event);

        $this->assertSame(1, AdmissionBridgeDeadLetter::count());
    }

    protected function consumer(): AdmissionBridgeConsumer
    {
        return new AdmissionBridgeConsumer(
            new AdmissionBridgeConfig,
            new AdmissionBindingResolver(new AdmissionBridgeConfig),
            new BridgeFormVersionGuard,
            new AdmissionBridgeRunRecorder,
            new AdmissionBridgeDeadLetterRecorder,
            new AdmissionMappingEngine(
                new AdmissionBridgeConfig,
                new BridgeMappingTransformApplicator,
                new BridgeMappingValidator,
            ),
            new BridgeCampusVisitCaseOrchestrator(
                new AdmissionReferenceService,
                new AdmissionPipelineService,
            ),
        );
    }

    protected function createPublishedForm(int $version = 2): Form
    {
        return Form::create([
            'name' => 'Campus Visit',
            'status' => 'enable',
            'publication_status' => 'published',
            'version' => $version,
        ]);
    }

    /**
     * @param  array<string, string>  $fieldMap
     */
    protected function setBinding(int $formId, array $fieldMap): void
    {
        Config::set('admissions_intake_bindings', [
            'campus_visit_primary' => [
                'binding_key' => 'campus_visit_primary',
                'enabled' => true,
                'form_id' => $formId,
                'mapped_form_version' => 2,
                'mapping_profile' => 'admissions_visit_v1',
                'field_map' => $fieldMap,
                'duplicate_policy' => 'same_cycle_link',
                'source_channel' => 'form_builder',
            ],
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function fieldMap(): array
    {
        return [
            'contact.name' => 'fld_1',
            'contact.phone' => 'fld_2',
            'contact.email' => 'fld_3',
            'applicant.first_name' => 'fld_4',
            'applicant.current_grade_label' => 'fld_5',
            'visit.scheduled_date' => 'fld_6',
            'visit.scheduled_time' => 'fld_7',
            'visit.notes' => 'fld_8',
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    protected function makeEvent(
        int $formId,
        int $submissionId = 1001,
        array $fields = [],
        bool $onlyProvidedFields = false,
    ): FormSubmissionFinalizedPayload {
        $defaults = [
            'fld_1' => 'Parent Name',
            'fld_2' => '01001234567',
            'fld_3' => 'parent@example.com',
            'fld_4' => 'Sara',
            'fld_5' => 'Grade 3',
            'fld_6' => '2026-07-01',
            'fld_7' => '10:30',
            'fld_8' => 'Notes',
        ];

        return new FormSubmissionFinalizedPayload(
            correlationId: (string) Str::uuid(),
            submissionId: $submissionId,
            formId: $formId,
            status: FormSubmissionStatus::SUBMITTED,
            finalizedAt: now()->toIso8601String(),
            locale: 'ar',
            channel: 'public',
            data: FormSubmissionSnapshot::attach(
                $onlyProvidedFields ? $fields : array_merge($defaults, $fields),
                [
                    'form_id' => $formId,
                    'form_version' => 2,
                    'snapshot_hash' => 'hash-1',
                    'captured_at' => now()->toIso8601String(),
                ],
                ['channel' => 'public'],
            ),
        );
    }
}
