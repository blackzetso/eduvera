<?php

namespace Tests\Unit\Admission\Bridge;

use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionCaseSubmission;
use App\Models\Admission\AdmissionContact;
use App\Services\Admission\AdmissionPipelineService;
use App\Services\Admission\AdmissionReferenceService;
use App\Services\Admission\Bridge\BridgeCampusVisitCaseOrchestrator;
use App\Support\Admission\AdmissionStatus;
use App\Support\Admission\Bridge\AdmissionBindingDefinition;
use App\Support\Admission\Bridge\AdmissionMappedPayload;
use App\Support\Admission\Bridge\BridgeRunOutcome;
use App\Support\FormBuilder\FormSubmissionFinalizedPayload;
use App\Support\FormBuilder\FormSubmissionSnapshot;
use App\Support\FormBuilder\FormSubmissionStatus;
use App\Support\Student\AcademicYear;
use Illuminate\Support\Str;
use Tests\Support\AdmissionBridgeTestSchema;
use Tests\TestCase;

class BridgeCampusVisitCaseOrchestratorTest extends TestCase
{
    use AdmissionBridgeTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureBridgeTestTables();
        $this->truncateBridgeTestTables();
    }

    public function test_creates_admission_case_and_submission_link(): void
    {
        $mapped = $this->mappedPayload();
        $event = $this->makeEvent();

        $result = $this->orchestrator()->orchestrate($mapped, $event, $this->binding());

        $this->assertTrue($result->success);
        $this->assertSame(BridgeRunOutcome::CASE_CREATED, $result->outcome);
        $this->assertDatabaseHas('admission_applications', [
            'id' => $result->admissionCaseId,
            'source_channel' => 'form_builder',
            'application_group_id' => null,
        ]);
        $this->assertDatabaseHas('admission_case_submissions', [
            'admission_application_id' => $result->admissionCaseId,
            'form_submission_id' => 3001,
        ]);
    }

    public function test_links_existing_case_for_same_cycle_duplicate_policy(): void
    {
        $existing = AdmissionApplication::create([
            'reference_code' => 'ADM-2026-00001',
            'application_group_id' => null,
            'pipeline_stage' => 'campus_visit',
            'status' => AdmissionStatus::OPEN,
            'academic_year' => AcademicYear::forDate(),
            'source_channel' => 'website_visit',
            'priority' => 'normal',
        ]);

        AdmissionContact::create([
            'admission_application_id' => $existing->id,
            'name' => 'Existing Parent',
            'phone' => '+201001234567',
            'email' => 'parent@example.com',
            'relationship_type' => 'guardian',
            'is_primary' => true,
        ]);

        $mapped = $this->mappedPayload(submissionId: 3002);
        $event = $this->makeEvent(submissionId: 3002);

        $result = $this->orchestrator()->orchestrate($mapped, $event, $this->binding());

        $this->assertTrue($result->success);
        $this->assertSame(BridgeRunOutcome::CASE_LINKED, $result->outcome);
        $this->assertSame($existing->id, $result->admissionCaseId);
        $this->assertSame(1, AdmissionApplication::count());
        $this->assertDatabaseHas('admission_case_submissions', [
            'admission_application_id' => $existing->id,
            'form_submission_id' => 3002,
        ]);
    }

    public function test_is_idempotent_for_same_submission_replay(): void
    {
        $mapped = $this->mappedPayload();
        $event = $this->makeEvent();

        $first = $this->orchestrator()->orchestrate($mapped, $event, $this->binding());
        $second = $this->orchestrator()->orchestrate($mapped, $event, $this->binding());

        $this->assertTrue($first->success);
        $this->assertTrue($second->success);
        $this->assertSame($first->admissionCaseId, $second->admissionCaseId);
        $this->assertSame(1, AdmissionCaseSubmission::count());
        $this->assertSame(1, AdmissionApplication::count());
    }

    protected function orchestrator(): BridgeCampusVisitCaseOrchestrator
    {
        return new BridgeCampusVisitCaseOrchestrator(
            new AdmissionReferenceService,
            new AdmissionPipelineService,
        );
    }

    protected function binding(): AdmissionBindingDefinition
    {
        return new AdmissionBindingDefinition(
            bindingKey: 'campus_visit_primary',
            enabled: true,
            formId: 10,
            mappedFormVersion: 2,
            mappingProfile: 'admissions_visit_v1',
            fieldMap: [],
            duplicatePolicy: 'same_cycle_link',
            sourceChannel: 'form_builder',
        );
    }

    protected function mappedPayload(int $submissionId = 3001): AdmissionMappedPayload
    {
        return new AdmissionMappedPayload(
            submissionId: $submissionId,
            formId: 10,
            bindingKey: 'campus_visit_primary',
            mappedFormVersion: 2,
            mappingProfile: 'admissions_visit_v1',
            normalizedData: [
                'contact' => [
                    'name' => 'Parent Name',
                    'phone' => '+201001234567',
                    'email' => 'parent@example.com',
                ],
                'applicant' => [
                    'first_name' => 'Sara',
                    'current_grade_label' => 'Grade 3',
                ],
                'visit' => [
                    'scheduled_date' => '2026-07-01',
                    'scheduled_time' => '10:30',
                    'notes' => 'Notes',
                ],
            ],
        );
    }

    protected function makeEvent(int $submissionId = 3001): FormSubmissionFinalizedPayload
    {
        return new FormSubmissionFinalizedPayload(
            correlationId: (string) Str::uuid(),
            submissionId: $submissionId,
            formId: 10,
            status: FormSubmissionStatus::SUBMITTED,
            finalizedAt: now()->toIso8601String(),
            locale: 'ar',
            channel: 'public',
            data: FormSubmissionSnapshot::attach(
                [],
                [
                    'form_id' => 10,
                    'form_version' => 2,
                    'snapshot_hash' => 'hash-1',
                    'captured_at' => now()->toIso8601String(),
                ],
                ['channel' => 'public'],
            ),
        );
    }
}
