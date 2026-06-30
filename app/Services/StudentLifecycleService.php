<?php

namespace App\Services;

use App\Models\User;
use App\Support\Student\StudentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentLifecycleService
{
    public function __construct(
        protected StudentEnrollmentService $enrollments,
        protected StudentStatusService $status,
        protected StudentGuardianService $guardians,
        protected PlatformAuditService $audit,
    ) {}

    public function availableActions(User $student): array
    {
        $status = $student->student_status ?? StudentStatus::ACTIVE;
        $hasCurrent = (bool) $this->enrollments->currentEnrollment($student);

        return [
            'promote' => $status === StudentStatus::ACTIVE && $hasCurrent,
            'transfer' => $status === StudentStatus::ACTIVE && $hasCurrent,
            'withdraw' => in_array($status, [StudentStatus::ACTIVE, StudentStatus::SUSPENDED], true) && $hasCurrent,
            're_enroll' => in_array($status, StudentStatus::reEnrollEligibleStatuses(), true) && ! $hasCurrent,
            'graduate' => $status === StudentStatus::ACTIVE && $hasCurrent,
            'change_status' => count(StudentStatus::transitionOptions($status)) > 0,
            'view_parent' => $student->guardians()->exists(),
        ];
    }

    public function promote(User $student, array $data): void
    {
        $this->assertActionAvailable($student, 'promote');
        $this->assertStudent($student);
        $before = ['status' => $student->student_status];

        if ($student->student_status !== StudentStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'status' => 'يمكن ترقية الطلاب النشطين فقط.',
            ]);
        }

        DB::transaction(function () use ($student, $data, $before) {
            $this->enrollments->promote(
                $student,
                (int) $data['category_id'],
                $data['academic_year'],
                $data['enrollment_date'] ?? null,
                $data['notes'] ?? null,
            );
            $this->audit->record('lifecycle', 'promote', $student, $before, [
                'status' => $student->fresh()->student_status,
                'category_id' => $data['category_id'],
            ]);
        });
    }

    /**
     * In-school class/section move: closes the current enrollment as "transferred"
     * and opens a new active enrollment. The student remains ACTIVE because they are
     * still enrolled at the institution. User-level TRANSFERRED status is reserved
     * for students who left the school (re-enroll eligible) and is set only via
     * manual status change, not this workflow.
     */
    public function transfer(User $student, array $data): void
    {
        $this->assertActionAvailable($student, 'transfer');
        $this->assertStudent($student);
        $before = ['status' => $student->student_status];

        if ($student->student_status !== StudentStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'status' => 'يمكن نقل الطلاب النشطين فقط.',
            ]);
        }

        DB::transaction(function () use ($student, $data, $before) {
            $this->enrollments->transfer(
                $student,
                (int) $data['category_id'],
                $data['transfer_date'],
                $data['reason'] ?? null,
                $data['notes'] ?? null,
            );
            $this->audit->record('lifecycle', 'transfer', $student, $before, [
                'status' => $student->fresh()->student_status,
                'category_id' => $data['category_id'],
            ]);
        });
    }

    public function withdraw(User $student, array $data): void
    {
        $this->assertActionAvailable($student, 'withdraw');
        $this->assertStudent($student);
        $before = ['status' => $student->student_status];

        if (! in_array($student->student_status, [StudentStatus::ACTIVE, StudentStatus::SUSPENDED], true)) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن إجراء الانسحاب لهذه الحالة.',
            ]);
        }

        DB::transaction(function () use ($student, $data, $before) {
            $this->enrollments->withdraw(
                $student,
                $data['withdrawal_date'],
                $data['reason'] ?? null,
                $data['notes'] ?? null,
            );

            $this->status->transition(
                $student->fresh(),
                StudentStatus::WITHDRAWN,
                $data['reason'] ?? null,
                $data['notes'] ?? null,
                isset($data['withdrawal_date']) ? new \DateTimeImmutable($data['withdrawal_date']) : null,
            );
            $this->audit->record('lifecycle', 'withdraw', $student, $before, [
                'status' => StudentStatus::WITHDRAWN,
            ]);
        });
    }

    public function reEnroll(User $student, array $data): void
    {
        $this->assertActionAvailable($student, 're_enroll');
        $this->assertStudent($student);
        $before = ['status' => $student->student_status];

        if (! in_array($student->student_status, StudentStatus::reEnrollEligibleStatuses(), true)) {
            throw ValidationException::withMessages([
                'status' => 'إعادة القيد متاحة للطلاب المنسحبين أو المحوّلين أو قيد الانتظار فقط.',
            ]);
        }

        if ($this->enrollments->currentEnrollment($student)) {
            throw ValidationException::withMessages([
                'enrollment' => 'لا يمكن إعادة قيد طالب لديه قيد نشط.',
            ]);
        }

        DB::transaction(function () use ($student, $data, $before) {
            $this->enrollments->reEnroll(
                $student,
                (int) $data['category_id'],
                $data['enrollment_date'] ?? null,
                $data['academic_year'] ?? null,
                $data['notes'] ?? null,
            );

            $this->status->transition(
                $student->fresh(),
                StudentStatus::ACTIVE,
                'إعادة قيد',
                $data['notes'] ?? null,
                isset($data['enrollment_date']) ? new \DateTimeImmutable($data['enrollment_date']) : null,
                validate: false,
            );
            $this->audit->record('lifecycle', 're_enroll', $student, $before, [
                'status' => StudentStatus::ACTIVE,
            ]);
        });
    }

    public function graduate(User $student, array $data): void
    {
        $this->assertActionAvailable($student, 'graduate');
        $this->assertStudent($student);
        $before = ['status' => $student->student_status];

        if ($student->student_status !== StudentStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'status' => 'يمكن تخريج الطلاب النشطين فقط.',
            ]);
        }

        DB::transaction(function () use ($student, $data, $before) {
            $this->enrollments->graduate(
                $student,
                $data['graduation_date'] ?? null,
                $data['notes'] ?? null,
            );

            $this->status->transition(
                $student->fresh(),
                StudentStatus::GRADUATED,
                null,
                $data['notes'] ?? null,
                isset($data['graduation_date']) ? new \DateTimeImmutable($data['graduation_date']) : null,
            );
            $this->audit->record('lifecycle', 'graduate', $student, $before, [
                'status' => StudentStatus::GRADUATED,
            ]);
        });
    }

    public function changeStatus(User $student, array $data): void
    {
        $this->assertActionAvailable($student, 'change_status');
        $this->assertStudent($student);
        $before = ['status' => $student->student_status];

        $this->status->transition(
            $student,
            $data['status'],
            $data['reason'] ?? null,
            $data['notes'] ?? null,
            isset($data['effective_date']) ? new \DateTimeImmutable($data['effective_date']) : null,
        );

        $this->audit->record('lifecycle', 'change_status', $student, $before, [
            'status' => $data['status'],
        ]);
    }

    public function updateGuardians(User $student, array $guardianLinks): void
    {
        $this->assertStudent($student);
        $before = ['guardian_ids' => $student->guardians()->pluck('users.id')->all()];
        $this->guardians->sync($student, $guardianLinks);
        $this->audit->record('lifecycle', 'link_guardian', $student, $before, [
            'guardian_ids' => collect($guardianLinks)->pluck('guardian_id')->all(),
        ]);
    }

    protected function assertActionAvailable(User $student, string $action): void
    {
        $actions = $this->availableActions($student);
        $key = $action === 're_enroll' ? 're_enroll' : ($action === 'change_status' ? 'change_status' : $action);

        if (! ($actions[$key] ?? false)) {
            throw ValidationException::withMessages([
                'action' => 'هذا الإجراء غير متاح للطالب في حالته الحالية.',
            ]);
        }
    }

    protected function assertStudent(User $student): void
    {
        if ($student->user_type !== 'student') {
            abort(404);
        }
    }
}
