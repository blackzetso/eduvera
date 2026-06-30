<?php

namespace App\Modules\Canteen\Integration\Adapters;

use App\Models\User;
use App\Modules\Canteen\Exceptions\StudentNotEligibleException;
use App\Modules\Canteen\Integration\Contracts\StudentIdentityPort;
use App\Modules\Canteen\Integration\DTOs\StudentSnapshot;
use App\Modules\Canteen\Services\CanteenStudentEligibilityService;
use App\Modules\Canteen\Services\CanteenStudentProfileSyncService;

class CoreStudentIdentityAdapter implements StudentIdentityPort
{
    public function __construct(
        protected CanteenStudentEligibilityService $eligibility,
        protected CanteenStudentProfileSyncService $profileSync,
    ) {}

    public function search(string $query, int $limit = 20): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $students = User::query()
            ->students()
            ->with('currentStudentEnrollment')
            ->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$query}%")
                ->orWhere('student_code', 'like', "%{$query}%")
                ->orWhere('id', $query)
                ->orWhereHas('currentStudentEnrollment', fn ($enrollment) => $enrollment
                    ->where('grade_name', 'like', "%{$query}%")
                    ->orWhere('class_name', 'like', "%{$query}%")))
            ->orderBy('name')
            ->limit($limit * 3)
            ->get();

        $results = [];

        foreach ($students as $student) {
            if (! $this->eligibility->canPurchase($student)) {
                continue;
            }

            $this->profileSync->syncFromUser($student);
            $results[] = $this->snapshot($student);

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    public function findByRef(string $studentIdRef): ?StudentSnapshot
    {
        $student = $this->resolveUser($studentIdRef);

        if (! $student) {
            return null;
        }

        try {
            $this->eligibility->assertCanPurchase($student);
        } catch (StudentNotEligibleException) {
            return null;
        }

        $this->profileSync->syncFromUser($student);

        return $this->snapshot($student);
    }

    protected function resolveUser(string $ref): ?User
    {
        $ref = trim($ref);

        if ($ref === '') {
            return null;
        }

        if (ctype_digit($ref)) {
            $byId = User::query()->students()->with('currentStudentEnrollment')->find((int) $ref);

            if ($byId) {
                return $byId;
            }
        }

        return User::query()
            ->students()
            ->with('currentStudentEnrollment')
            ->where('student_code', $ref)
            ->first();
    }

    protected function snapshot(User $student): StudentSnapshot
    {
        $enrollment = $student->currentStudentEnrollment;

        return new StudentSnapshot(
            (string) $student->id,
            $student->name,
            $enrollment?->grade_name,
            $enrollment?->class_name,
        );
    }
}
