<?php

namespace App\Modules\Canteen\Services;

use App\Models\User;
use App\Modules\Canteen\Models\StudentProfile;

class GuardianCanteenStudentService
{
    public function profileForStudent(User $student): ?StudentProfile
    {
        return StudentProfile::query()
            ->where(fn ($q) => $q
                ->where('user_id', $student->id)
                ->orWhere('student_id_ref', (string) $student->id))
            ->first();
    }

    public function studentIdRef(User $student): string
    {
        return (string) $student->id;
    }

    public function ensureProfile(User $student): StudentProfile
    {
        $profile = $this->profileForStudent($student);

        if ($profile) {
            return $profile;
        }

        return StudentProfile::query()->create([
            'user_id' => $student->id,
            'student_id_ref' => (string) $student->id,
            'student_name' => $student->name,
            'is_active' => true,
        ]);
    }
}
