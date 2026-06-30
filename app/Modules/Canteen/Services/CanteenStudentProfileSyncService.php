<?php

namespace App\Modules\Canteen\Services;

use App\Models\User;
use App\Modules\Canteen\Models\StudentProfile;
use App\Services\StudentEnrollmentService;

class CanteenStudentProfileSyncService
{
    public function __construct(
        protected StudentEnrollmentService $enrollments,
        protected CanteenGuardianProfileSyncService $guardianSync,
    ) {}

    public function syncFromUser(User $student): StudentProfile
    {
        $enrollment = $this->enrollments->currentEnrollment($student);
        $studentIdRef = (string) $student->id;

        $profile = StudentProfile::query()
            ->where(fn ($q) => $q
                ->where('user_id', $student->id)
                ->orWhere('student_id_ref', $studentIdRef))
            ->first();

        $attributes = [
            'user_id' => $student->id,
            'student_id_ref' => $studentIdRef,
            'student_name' => $student->name,
            'grade' => $enrollment?->grade_name,
            'class_name' => $enrollment?->class_name,
            'last_synced_at' => now(),
        ];

        if ($profile) {
            $profile->update($attributes);
        } else {
            $profile = StudentProfile::query()->create($attributes + [
                'is_active' => true,
            ]);
        }

        return $this->guardianSync->syncForStudent($student, $profile->fresh());
    }
}
