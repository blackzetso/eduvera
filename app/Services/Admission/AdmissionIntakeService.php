<?php

namespace App\Services\Admission;

use App\Mail\AdmissionVisitConfirmationMail;
use App\Models\Admission\AdmissionApplication;
use App\Support\Admission\AdmissionEngagementChannel;
use App\Support\Admission\AdmissionEngagementStatus;
use App\Support\Admission\AdmissionEngagementType;
use App\Support\Admission\AdmissionReadinessPolicy;
use App\Support\Admission\AdmissionStage;
use App\Support\Admission\AdmissionStatus;
use App\Support\Student\AcademicYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdmissionIntakeService
{
    public function __construct(
        protected AdmissionReferenceService $references,
        protected AdmissionPipelineService $pipeline,
        protected AdmissionReadinessPolicy $readiness,
        protected AdmissionEngagementService $engagements,
        protected AdmissionDocumentService $documents,
    ) {}

    /**
     * Persist a website campus visit request into the admissions intake layer.
     */
    public function processVisitRequest(array $payload, ?string $sourceReference = null): AdmissionApplication
    {
        $parentName = $this->field($payload, ['parentName', 'parent_name']);
        $studentName = $this->field($payload, ['studentName', 'student_name']);
        $currentGrade = $this->field($payload, ['currentGrade', 'current_grade']);
        $phone = $this->field($payload, ['phone', 'parent_phone']);
        $email = $this->field($payload, ['email', 'parent_email']);
        $visitDate = $this->field($payload, ['visitDate', 'visit_date']);
        $visitTime = $this->field($payload, ['visitTime', 'visit_time']);
        $notes = $this->field($payload, ['notes', 'message', 'additional_notes']);

        return DB::transaction(function () use (
            $parentName,
            $studentName,
            $currentGrade,
            $phone,
            $email,
            $visitDate,
            $visitTime,
            $notes,
            $sourceReference,
            $payload,
        ) {
            $application = AdmissionApplication::create([
                'reference_code' => $this->references->generate(),
                'application_group_id' => (string) Str::uuid(),
                'pipeline_stage' => AdmissionStage::CAMPUS_VISIT,
                'status' => AdmissionStatus::OPEN,
                'academic_year' => AcademicYear::forDate(),
                'source_channel' => 'website_visit',
                'source_reference' => $sourceReference ?? ($payload['formId'] ?? null),
                'priority' => 'normal',
                'notes' => $notes,
            ]);

            $application->applicants()->create([
                'first_name' => $studentName ?: '—',
                'current_grade_label' => $currentGrade,
            ]);

            $application->contacts()->create([
                'name' => $parentName ?: '—',
                'email' => $email,
                'phone' => $phone,
                'relationship_type' => 'guardian',
                'is_primary' => true,
                'is_emergency_contact' => true,
                'is_pickup_authorized' => true,
                'is_financial_responsible' => true,
            ]);

            $application->load(['applicants', 'contacts']);

            if ($visitDate || $visitTime) {
                $this->readiness->assertReady($application, AdmissionReadinessPolicy::CONTEXT_VISIT_SCHEDULE);
            }

            $application->visits()->create([
                'scheduled_date' => $visitDate ?: null,
                'scheduled_time' => $visitTime ?: null,
                'status' => 'requested',
                'notes' => $notes,
            ]);

            $this->pipeline->recordInitialStage(
                $application,
                AdmissionStage::CAMPUS_VISIT,
                'website_visit_intake',
                'Campus visit requested via website form',
            );

            $this->documents->ensureChecklist($application);

            $visit = $application->visits()->first();

            $this->engagements->record([
                'admission_application_id' => $application->id,
                'type' => AdmissionEngagementType::WEBSITE_FORM,
                'channel' => AdmissionEngagementChannel::WEBSITE,
                'status' => AdmissionEngagementStatus::COMPLETED,
                'subject' => 'استفسار من الموقع',
                'message' => $notes,
                'completed_at' => now(),
                'metadata' => [
                    'source_key' => "application:{$application->id}:website_form",
                    'source_reference' => $sourceReference,
                ],
            ]);

            if ($visit) {
                $scheduledAt = $visitDate
                    ? \Illuminate\Support\Carbon::parse(trim(($visitDate ?: now()->toDateString()).' '.($visitTime ?: '09:00')))
                    : now();

                $this->engagements->schedule($application, [
                    'type' => AdmissionEngagementType::CAMPUS_VISIT,
                    'channel' => AdmissionEngagementChannel::VISIT,
                    'subject' => 'زيارة الحرم',
                    'message' => $notes,
                    'scheduled_at' => $scheduledAt,
                    'metadata' => [
                        'source_key' => "visit:{$visit->id}:scheduled",
                        'visit_id' => $visit->id,
                    ],
                ]);
            }

            return $application->load([
                'primaryApplicant',
                'primaryContact',
                'latestVisit',
            ]);
        });
    }

    public function sendConfirmationEmail(AdmissionApplication $application, ?string $parentEmail): bool
    {
        if (! $parentEmail) {
            return false;
        }

        try {
            Mail::to($parentEmail)->send(
                new AdmissionVisitConfirmationMail($application, AdmissionVisitConfirmationMail::schoolName())
            );

            return true;
        } catch (\Throwable $e) {
            Log::warning('admission_visit_confirmation_email_failed', [
                'application_id' => $application->id,
                'email' => $parentEmail,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function field(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if ($value !== null && $value !== '') {
                return is_string($value) ? trim($value) : (string) $value;
            }
        }

        return null;
    }
}
