<?php

namespace App\Services\Admission\Bridge;

use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionCaseSubmission;
use App\Services\Admission\AdmissionPipelineService;
use App\Services\Admission\AdmissionReferenceService;
use App\Support\Admission\AdmissionStage;
use App\Support\Admission\AdmissionStatus;
use App\Support\Admission\Bridge\AdmissionBindingDefinition;
use App\Support\Admission\Bridge\AdmissionMappedPayload;
use App\Support\Admission\Bridge\BridgeCampusVisitOrchestratorResult;
use App\Support\Admission\Bridge\BridgeErrorCode;
use App\Support\Admission\Bridge\BridgeRunOutcome;
use App\Support\FormBuilder\FormSubmissionFinalizedPayload;
use App\Support\Student\AcademicYear;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BridgeCampusVisitCaseOrchestrator
{
    public function __construct(
        protected AdmissionReferenceService $references,
        protected AdmissionPipelineService $pipeline,
    ) {}

    public function orchestrate(
        AdmissionMappedPayload $mapped,
        FormSubmissionFinalizedPayload $event,
        AdmissionBindingDefinition $binding,
    ): BridgeCampusVisitOrchestratorResult {
        $existingLink = AdmissionCaseSubmission::query()
            ->where('form_submission_id', $mapped->submissionId)
            ->first();

        if ($existingLink !== null) {
            return BridgeCampusVisitOrchestratorResult::succeeded(
                (int) $existingLink->admission_application_id,
                BridgeRunOutcome::CASE_LINKED,
            );
        }

        return match ($binding->duplicatePolicy) {
            'same_cycle_link' => $this->orchestrateSameCycleLink($mapped, $event, $binding),
            default => BridgeCampusVisitOrchestratorResult::failed(
                BridgeErrorCode::DUPLICATE_POLICY_UNSUPPORTED,
                'Unsupported duplicate policy: '.$binding->duplicatePolicy,
            ),
        };
    }

    protected function orchestrateSameCycleLink(
        AdmissionMappedPayload $mapped,
        FormSubmissionFinalizedPayload $event,
        AdmissionBindingDefinition $binding,
    ): BridgeCampusVisitOrchestratorResult {
        try {
            return DB::transaction(function () use ($mapped, $event, $binding) {
                $existingLink = AdmissionCaseSubmission::query()
                    ->where('form_submission_id', $mapped->submissionId)
                    ->lockForUpdate()
                    ->first();

                if ($existingLink !== null) {
                    return BridgeCampusVisitOrchestratorResult::succeeded(
                        (int) $existingLink->admission_application_id,
                        BridgeRunOutcome::CASE_LINKED,
                    );
                }

                $existingCase = $this->resolveExistingCase($mapped, $binding);

                if ($existingCase !== null) {
                    $this->createCaseSubmissionLink($existingCase, $mapped, $event);

                    return BridgeCampusVisitOrchestratorResult::succeeded(
                        $existingCase->id,
                        BridgeRunOutcome::CASE_LINKED,
                    );
                }

                $created = $this->createAdmissionCase($mapped, $event, $binding);
                $this->createCaseSubmissionLink($created, $mapped, $event);

                return BridgeCampusVisitOrchestratorResult::succeeded(
                    $created->id,
                    BridgeRunOutcome::CASE_CREATED,
                );
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueSubmissionLinkViolation($exception)) {
                $existingLink = AdmissionCaseSubmission::query()
                    ->where('form_submission_id', $mapped->submissionId)
                    ->first();

                if ($existingLink !== null) {
                    return BridgeCampusVisitOrchestratorResult::succeeded(
                        (int) $existingLink->admission_application_id,
                        BridgeRunOutcome::CASE_LINKED,
                    );
                }
            }

            Log::error('Admission bridge orchestration failed with database error.', [
                'submission_id' => $mapped->submissionId,
                'message' => $exception->getMessage(),
            ]);

            return BridgeCampusVisitOrchestratorResult::failed(
                BridgeErrorCode::ORCHESTRATION_FAILED,
                $exception->getMessage(),
            );
        } catch (\Throwable $exception) {
            Log::error('Admission bridge orchestration failed.', [
                'submission_id' => $mapped->submissionId,
                'message' => $exception->getMessage(),
            ]);

            return BridgeCampusVisitOrchestratorResult::failed(
                BridgeErrorCode::ORCHESTRATION_FAILED,
                $exception->getMessage(),
            );
        }
    }

    protected function resolveExistingCase(
        AdmissionMappedPayload $mapped,
        AdmissionBindingDefinition $binding,
    ): ?AdmissionApplication {
        $contact = $mapped->normalizedData['contact'] ?? [];
        $phone = is_array($contact) ? ($contact['phone'] ?? null) : null;
        $email = is_array($contact) ? ($contact['email'] ?? null) : null;

        if (! is_string($phone) && ! is_string($email)) {
            return null;
        }

        $academicYear = $this->resolveAcademicYear($binding);

        $query = AdmissionApplication::query()
            ->where('status', AdmissionStatus::OPEN)
            ->where('academic_year', $academicYear)
            ->where(function ($builder) use ($phone, $email) {
                if (is_string($phone) && $phone !== '') {
                    $builder->orWhereHas('contacts', fn ($contactQuery) => $contactQuery->where('phone', $phone));
                }

                if (is_string($email) && $email !== '') {
                    $builder->orWhereHas('contacts', fn ($contactQuery) => $contactQuery->where('email', $email));
                }
            })
            ->orderByDesc('id');

        return $query->first();
    }

    protected function createAdmissionCase(
        AdmissionMappedPayload $mapped,
        FormSubmissionFinalizedPayload $event,
        AdmissionBindingDefinition $binding,
    ): AdmissionApplication {
        $contact = is_array($mapped->normalizedData['contact'] ?? null)
            ? $mapped->normalizedData['contact']
            : [];
        $applicant = is_array($mapped->normalizedData['applicant'] ?? null)
            ? $mapped->normalizedData['applicant']
            : [];
        $visit = is_array($mapped->normalizedData['visit'] ?? null)
            ? $mapped->normalizedData['visit']
            : [];

        $application = AdmissionApplication::create([
            'reference_code' => $this->references->generate(),
            'application_group_id' => null,
            'pipeline_stage' => $binding->v1PipelineStage,
            'status' => AdmissionStatus::OPEN,
            'academic_year' => $this->resolveAcademicYear($binding),
            'source_channel' => $binding->sourceChannel,
            'source_reference' => (string) $event->submissionId,
            'priority' => 'normal',
            'notes' => $visit['notes'] ?? null,
        ]);

        $application->applicants()->create([
            'first_name' => $applicant['first_name'] ?? '—',
            'current_grade_label' => $applicant['current_grade_label'] ?? null,
        ]);

        $application->contacts()->create([
            'name' => $contact['name'] ?? '—',
            'email' => $contact['email'] ?? null,
            'phone' => $contact['phone'] ?? null,
            'relationship_type' => 'guardian',
            'is_primary' => true,
            'is_emergency_contact' => true,
            'is_pickup_authorized' => true,
            'is_financial_responsible' => true,
        ]);

        if (($visit['scheduled_date'] ?? null) || ($visit['scheduled_time'] ?? null) || ($visit['notes'] ?? null)) {
            $application->visits()->create([
                'scheduled_date' => $visit['scheduled_date'] ?? null,
                'scheduled_time' => $visit['scheduled_time'] ?? null,
                'status' => 'requested',
                'notes' => $visit['notes'] ?? null,
            ]);
        }

        $this->pipeline->recordInitialStage(
            $application,
            AdmissionStage::CAMPUS_VISIT,
            'form_builder_bridge',
            'Campus visit submitted via form builder bridge',
        );

        return $application->fresh(['primaryApplicant', 'primaryContact', 'latestVisit']);
    }

    protected function createCaseSubmissionLink(
        AdmissionApplication $application,
        AdmissionMappedPayload $mapped,
        FormSubmissionFinalizedPayload $event,
    ): AdmissionCaseSubmission {
        return AdmissionCaseSubmission::create([
            'admission_application_id' => $application->id,
            'form_submission_id' => $mapped->submissionId,
            'correlation_id' => $event->correlationId,
        ]);
    }

    protected function resolveAcademicYear(AdmissionBindingDefinition $binding): string
    {
        return match ($binding->cycleScope) {
            'academic_year' => AcademicYear::forDate(),
            default => AcademicYear::forDate(),
        };
    }

    protected function isUniqueSubmissionLinkViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique')
            && str_contains($message, 'form_submission_id');
    }
}
