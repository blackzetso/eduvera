<?php

namespace Tests\Unit\Admission\Bridge;

use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionBridgeDeadLetter;
use App\Models\Admission\AdmissionBridgeRun;
use App\Models\Admission\AdmissionCaseSubmission;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Support\Admission\AdmissionStage;
use App\Support\Admission\AdmissionStatus;
use App\Support\Admission\Bridge\BridgeRunOutcome;
use App\Support\Admission\Bridge\BridgeRunStatus;
use Illuminate\Support\Str;
use Tests\Support\AdmissionBridgeTestSchema;
use Tests\TestCase;

class AdmissionBridgePersistenceTest extends TestCase
{
    use AdmissionBridgeTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureBridgeTestTables();
        $this->truncateBridgeTestTables();
    }

    public function test_bridge_run_can_be_created(): void
    {
        [$submission, $form, $application] = $this->seedSubmissionAndCase();

        $run = AdmissionBridgeRun::create([
            'submission_id' => $submission->id,
            'correlation_id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'binding_key' => 'campus_visit_primary',
            'mapped_form_version' => 2,
            'mapping_profile' => 'admissions_visit_v1',
            'status' => BridgeRunStatus::COMPLETED,
            'outcome' => BridgeRunOutcome::CASE_CREATED,
            'admission_case_id' => $application->id,
            'duration_ms' => 42,
            'processed_at' => now(),
        ]);

        $this->assertDatabaseHas('admission_bridge_runs', [
            'id' => $run->id,
            'submission_id' => $submission->id,
            'binding_key' => 'campus_visit_primary',
            'status' => BridgeRunStatus::COMPLETED,
        ]);

        $this->assertSame($submission->id, $run->fresh()->submission->id);
        $this->assertSame($application->id, $run->fresh()->admissionCase->id);
    }

    public function test_unique_submission_constraint_on_bridge_runs(): void
    {
        [$submission, $form] = $this->seedSubmissionAndCase();

        AdmissionBridgeRun::create([
            'submission_id' => $submission->id,
            'correlation_id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'binding_key' => 'campus_visit_primary',
            'mapped_form_version' => 2,
            'mapping_profile' => 'admissions_visit_v1',
            'status' => BridgeRunStatus::PENDING,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        AdmissionBridgeRun::create([
            'submission_id' => $submission->id,
            'correlation_id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'binding_key' => 'campus_visit_primary',
            'mapped_form_version' => 2,
            'mapping_profile' => 'admissions_visit_v1',
            'status' => BridgeRunStatus::FAILED,
        ]);
    }

    public function test_case_submission_link_can_be_created(): void
    {
        [$submission, , $application] = $this->seedSubmissionAndCase();
        $correlationId = (string) Str::uuid();

        $link = AdmissionCaseSubmission::create([
            'admission_application_id' => $application->id,
            'form_submission_id' => $submission->id,
            'correlation_id' => $correlationId,
        ]);

        $this->assertDatabaseHas('admission_case_submissions', [
            'id' => $link->id,
            'form_submission_id' => $submission->id,
            'admission_application_id' => $application->id,
        ]);
    }

    public function test_unique_form_submission_constraint_on_case_links(): void
    {
        [$submission, , $application] = $this->seedSubmissionAndCase();

        AdmissionCaseSubmission::create([
            'admission_application_id' => $application->id,
            'form_submission_id' => $submission->id,
            'correlation_id' => (string) Str::uuid(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        AdmissionCaseSubmission::create([
            'admission_application_id' => $application->id,
            'form_submission_id' => $submission->id,
            'correlation_id' => (string) Str::uuid(),
        ]);
    }

    public function test_dead_letter_can_be_created(): void
    {
        [$submission, $form] = $this->seedSubmissionAndCase();

        $dlq = AdmissionBridgeDeadLetter::create([
            'submission_id' => $submission->id,
            'correlation_id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'binding_key' => 'campus_visit_primary',
            'error_code' => 'BRIDGE_MAP_VERSION_MISMATCH',
            'error_message' => 'Version mismatch',
            'retry_count' => 0,
            'event_payload' => ['event' => 'form_submission.finalized'],
            'failed_at' => now(),
        ]);

        $this->assertDatabaseHas('admission_bridge_dead_letters', [
            'id' => $dlq->id,
            'submission_id' => $submission->id,
            'error_code' => 'BRIDGE_MAP_VERSION_MISMATCH',
        ]);
    }

    /**
     * @return array{0: FormSubmission, 1: Form, 2: AdmissionApplication}
     */
    protected function seedSubmissionAndCase(): array
    {
        $form = Form::create([
            'name' => 'Campus Visit',
            'status' => 'enable',
            'publication_status' => 'published',
            'version' => 2,
        ]);

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'status' => 'submitted',
            'data' => ['fld_1' => 'Test'],
            'locale' => 'ar',
        ]);

        $application = AdmissionApplication::create([
            'reference_code' => 'ADM-2026-99999',
            'pipeline_stage' => AdmissionStage::CAMPUS_VISIT,
            'status' => AdmissionStatus::OPEN,
            'academic_year' => '2026',
            'source_channel' => 'form_builder',
            'source_reference' => 'form_submission:'.$submission->id,
        ]);

        return [$submission, $form, $application];
    }
}
