<?php

namespace App\Modules\Canteen\Services;

use App\Models\User;
use App\Modules\Canteen\Exceptions\StudentNotEligibleException;
use App\Modules\Canteen\Integration\Contracts\GuardianIntegrationPort;
use App\Modules\Canteen\Models\StudentProfile;
use App\Services\StudentEnrollmentService;
use App\Support\Student\StudentStatus;

class CanteenStudentEligibilityService
{
    public function __construct(
        protected StudentEnrollmentService $enrollments,
        protected GuardianIntegrationPort $guardians,
        protected CanteenHealthRestrictionService $healthRestrictions,
        protected CanteenGuardianSpendingService $guardianSpending,
    ) {}

    public function assertCanPurchase(User $student): void
    {
        $reason = $this->purchaseBlockReason($student);

        if ($reason !== null) {
            throw new StudentNotEligibleException($reason);
        }
    }

    public function canPurchase(User $student): bool
    {
        return $this->purchaseBlockReason($student) === null;
    }

    public function purchaseBlockReason(User $student): ?string
    {
        if ($student->user_type !== 'student') {
            return 'User is not a student.';
        }

        $status = $student->student_status ?? StudentStatus::ACTIVE;

        if ($status !== StudentStatus::ACTIVE) {
            return match ($status) {
                StudentStatus::WITHDRAWN => 'Student is withdrawn and cannot purchase.',
                StudentStatus::GRADUATED => 'Student has graduated and cannot purchase.',
                StudentStatus::SUSPENDED => 'Student is suspended and cannot purchase.',
                StudentStatus::TRANSFERRED => 'Student is transferred and cannot purchase.',
                StudentStatus::PENDING => 'Student is pending activation and cannot purchase.',
                default => 'Student is not active and cannot purchase.',
            };
        }

        $enrollment = $this->enrollments->currentEnrollment($student);

        if (! $enrollment) {
            return 'Student has no current enrollment.';
        }

        if ($enrollment->status !== 'active') {
            return 'Student enrollment is not active.';
        }

        $profile = $this->canteenProfileForStudent($student);

        if ($profile && ! $profile->is_active) {
            return 'Student canteen profile is inactive.';
        }

        if (config('canteen.guardian.require_linked_guardian', false)) {
            $primaryGuardian = $this->guardians->resolvePrimaryGuardian($student);

            if (! $primaryGuardian) {
                return 'Student has no linked guardian and cannot purchase.';
            }
        }

        $healthBlock = $this->healthRestrictions->blockReasonForStudent($profile);

        if ($healthBlock) {
            return $healthBlock;
        }

        $householdLimit = $profile?->metadata['guardian_daily_limit'] ?? null;

        if ($householdLimit !== null && $profile?->primary_guardian_user_id) {
            $guardian = User::query()->find($profile->primary_guardian_user_id);

            if ($guardian) {
                $check = $this->guardianSpending->canHouseholdSpend($guardian, '0.01');

                if (! $check['allowed']) {
                    return 'Guardian household daily spending limit has been reached.';
                }
            }
        }

        return null;
    }

    protected function canteenProfileForStudent(User $student): ?StudentProfile
    {
        return StudentProfile::query()
            ->where(fn ($q) => $q
                ->where('user_id', $student->id)
                ->orWhere('student_id_ref', (string) $student->id))
            ->first();
    }
}
