<?php

namespace App\Services\Admission;

use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionDecisionHistory;
use App\Support\Admission\AdmissionDecision;
use App\Support\Admission\AdmissionReadinessPolicy;
use App\Support\Admission\AdmissionStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdmissionDecisionService
{
    public function __construct(
        protected AdmissionReadinessPolicy $readiness,
        protected \App\Services\PlatformAuditService $audit,
    ) {}

    public function accept(AdmissionApplication $application, ?string $reason = null, ?string $notes = null, ?int $userId = null): AdmissionApplication
    {
        $this->readiness->assertReady($application, AdmissionReadinessPolicy::CONTEXT_DECISION);

        return $this->record($application, AdmissionDecision::ACCEPTED, AdmissionStatus::OPEN, $reason, $notes, $userId);
    }

    public function reject(AdmissionApplication $application, ?string $reason = null, ?string $notes = null, ?int $userId = null): AdmissionApplication
    {
        return $this->record($application, AdmissionDecision::REJECTED, AdmissionStatus::REJECTED, $reason, $notes, $userId);
    }

    public function waitlist(AdmissionApplication $application, ?string $reason = null, ?string $notes = null, ?int $userId = null): AdmissionApplication
    {
        return $this->record($application, AdmissionDecision::WAITLISTED, AdmissionStatus::WAITLISTED, $reason, $notes, $userId);
    }

    public function withdraw(AdmissionApplication $application, ?string $reason = null, ?string $notes = null, ?int $userId = null): AdmissionApplication
    {
        return $this->record($application, AdmissionDecision::WITHDRAWN, AdmissionStatus::WITHDRAWN, $reason, $notes, $userId);
    }

    public function record(
        AdmissionApplication $application,
        string $toDecision,
        string $status,
        ?string $reason = null,
        ?string $notes = null,
        ?int $userId = null,
    ): AdmissionApplication {
        $this->assertCanChangeDecision($application);

        if (! in_array($toDecision, AdmissionDecision::all(), true) || $toDecision === AdmissionDecision::CONVERTED) {
            throw ValidationException::withMessages([
                'decision' => 'قرار غير صالح.',
            ]);
        }

        if ($application->decision === $toDecision) {
            throw ValidationException::withMessages([
                'decision' => 'الطلب في هذا القرار بالفعل.',
            ]);
        }

        $fromDecision = $application->decision;
        $fromStatus = $application->status;
        $performedBy = $userId ?? Auth::id();
        $effectiveAt = now();

        $application->update([
            'decision' => $toDecision,
            'decision_at' => $effectiveAt,
            'decision_by_user_id' => $performedBy,
            'status' => $status,
        ]);

        AdmissionDecisionHistory::create([
            'admission_application_id' => $application->id,
            'from_decision' => $fromDecision,
            'to_decision' => $toDecision,
            'reason' => $reason,
            'notes' => $notes,
            'performed_by_user_id' => $performedBy,
            'effective_at' => $effectiveAt,
        ]);

        $auditAction = match ($toDecision) {
            AdmissionDecision::ACCEPTED => 'decision_accept',
            AdmissionDecision::REJECTED => 'decision_reject',
            AdmissionDecision::WAITLISTED => 'decision_waitlist',
            AdmissionDecision::WITHDRAWN => 'decision_withdraw',
            default => 'decision_'.$toDecision,
        };

        $this->audit->record(
            'admissions',
            $auditAction,
            $application,
            ['decision' => $fromDecision, 'status' => $fromStatus],
            ['decision' => $toDecision, 'status' => $status],
            ['reason' => $reason, 'notes' => $notes],
            $performedBy,
        );

        return $application->fresh();
    }

    public function assertCanChangeDecision(AdmissionApplication $application): void
    {
        if ($application->decision === AdmissionDecision::CONVERTED || $application->converted_student_id) {
            throw ValidationException::withMessages([
                'application' => 'لا يمكن تغيير قرار طلب تم تحويله.',
            ]);
        }
    }
}
