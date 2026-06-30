<?php

namespace Tests\Unit\Admission\Bridge;

use App\Models\Admission\AdmissionBridgeDeadLetter;
use App\Models\Admission\AdmissionBridgeRun;
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

class AdmissionBridgeConsumerTest extends TestCase
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

    public function test_ignores_event_when_no_binding_matches_form(): void
    {
        $event = $this->makeEvent(formId: 999);

        $result = $this->consumer()->consume($event);

        $this->assertSame(AdmissionBridgeConsumerResult::IGNORED, $result->status);
        $this->assertDatabaseCount('admission_bridge_runs', 0);
    }

    public function test_records_skipped_run_for_inactive_binding(): void
    {
        $form = Form::create([
            'name' => 'Campus Visit',
            'status' => 'enable',
            'publication_status' => 'draft',
            'version' => 2,
        ]);

        $this->setBinding($form->id, enabled: true, mappedVersion: 2);
        $event = $this->makeEvent(formId: $form->id, formVersion: 2);

        $result = $this->consumer()->consume($event);

        $this->assertSame(AdmissionBridgeConsumerResult::SKIPPED, $result->status);
        $this->assertDatabaseHas('admission_bridge_runs', [
            'submission_id' => $event->submissionId,
            'status' => BridgeRunStatus::SKIPPED,
            'outcome' => BridgeRunOutcome::NO_OP,
            'error_code' => BridgeErrorCode::BINDING_INACTIVE,
        ]);
    }

    public function test_records_completed_run_when_mapping_and_orchestration_succeed(): void
    {
        $form = $this->createPublishedForm(version: 2);
        $this->setBinding($form->id, enabled: true, mappedVersion: 2, fieldMap: $this->fieldMap());
        $event = $this->makeEvent(formId: $form->id, formVersion: 2);

        $result = $this->consumer()->consume($event);

        $this->assertSame(AdmissionBridgeConsumerResult::COMPLETED, $result->status);
        $this->assertDatabaseHas('admission_bridge_runs', [
            'submission_id' => $event->submissionId,
            'status' => BridgeRunStatus::COMPLETED,
            'outcome' => BridgeRunOutcome::CASE_CREATED,
            'binding_key' => 'campus_visit_primary',
            'mapped_form_version' => 2,
        ]);
    }

    public function test_records_failed_run_when_field_map_is_incomplete(): void
    {
        $form = $this->createPublishedForm(version: 2);
        $this->setBinding($form->id, enabled: true, mappedVersion: 2, fieldMap: []);
        $event = $this->makeEvent(formId: $form->id, formVersion: 2);

        $result = $this->consumer()->consume($event);

        $this->assertSame(AdmissionBridgeConsumerResult::DEAD_LETTERED, $result->status);
        $this->assertDatabaseHas('admission_bridge_runs', [
            'submission_id' => $event->submissionId,
            'status' => BridgeRunStatus::FAILED,
            'error_code' => BridgeErrorCode::MAP_INCOMPLETE,
        ]);
    }

    public function test_version_mismatch_writes_dlq_and_failed_run(): void
    {
        $form = $this->createPublishedForm(version: 2);
        $this->setBinding($form->id, enabled: true, mappedVersion: 2);
        $event = $this->makeEvent(formId: $form->id, formVersion: 3);

        $result = $this->consumer()->consume($event);

        $this->assertSame(AdmissionBridgeConsumerResult::DEAD_LETTERED, $result->status);
        $this->assertDatabaseHas('admission_bridge_runs', [
            'submission_id' => $event->submissionId,
            'status' => BridgeRunStatus::FAILED,
            'error_code' => BridgeErrorCode::MAP_VERSION_MISMATCH,
        ]);
        $this->assertSame(1, AdmissionBridgeDeadLetter::count());
    }

    public function test_idempotency_resumes_terminal_run_without_duplicate_rows(): void
    {
        $form = $this->createPublishedForm(version: 2);
        $this->setBinding($form->id, enabled: true, mappedVersion: 2);
        $event = $this->makeEvent(formId: $form->id, formVersion: 2);

        AdmissionBridgeRun::create([
            'submission_id' => $event->submissionId,
            'correlation_id' => $event->correlationId,
            'form_id' => $form->id,
            'binding_key' => 'campus_visit_primary',
            'mapped_form_version' => 2,
            'mapping_profile' => 'admissions_visit_v1',
            'status' => BridgeRunStatus::SKIPPED,
            'outcome' => BridgeRunOutcome::NO_OP,
            'processed_at' => now(),
        ]);

        $result = $this->consumer()->consume($event);

        $this->assertSame(AdmissionBridgeConsumerResult::RESUMED, $result->status);
        $this->assertSame(1, AdmissionBridgeRun::count());
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
    protected function setBinding(int $formId, bool $enabled, int $mappedVersion, array $fieldMap = []): void
    {
        Config::set('admissions_intake_bindings', [
            'campus_visit_primary' => [
                'binding_key' => 'campus_visit_primary',
                'enabled' => $enabled,
                'form_id' => $formId,
                'mapped_form_version' => $mappedVersion,
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

    protected function makeEvent(int $formId, int $formVersion = 2): FormSubmissionFinalizedPayload
    {
        return new FormSubmissionFinalizedPayload(
            correlationId: (string) Str::uuid(),
            submissionId: 1001,
            formId: $formId,
            status: FormSubmissionStatus::SUBMITTED,
            finalizedAt: now()->toIso8601String(),
            locale: 'ar',
            channel: 'public',
            data: FormSubmissionSnapshot::attach(
                [
                    'fld_1' => 'Parent Name',
                    'fld_2' => '01001234567',
                    'fld_3' => 'parent@example.com',
                    'fld_4' => 'Sara',
                    'fld_5' => 'Grade 3',
                    'fld_6' => '2026-07-01',
                    'fld_7' => '10:30',
                    'fld_8' => 'Notes',
                ],
                [
                    'form_id' => $formId,
                    'form_version' => $formVersion,
                    'snapshot_hash' => 'hash-1',
                    'captured_at' => now()->toIso8601String(),
                ],
                ['channel' => 'public'],
            ),
        );
    }
}
