<?php

namespace App\Modules\Canteen\Integration\Adapters;

use App\Modules\Canteen\Integration\Contracts\StudentIdentityPort;
use App\Modules\Canteen\Integration\DTOs\StudentSnapshot;
use App\Modules\Canteen\Models\StudentProfile;

class LocalSnapshotStudentAdapter implements StudentIdentityPort
{
    public function search(string $query, int $limit = 20): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        return StudentProfile::query()
            ->where('is_active', true)
            ->where(fn ($b) => $b
                ->where('student_id_ref', 'like', "%{$query}%")
                ->orWhere('student_name', 'like', "%{$query}%")
                ->orWhere('class_name', 'like', "%{$query}%"))
            ->orderBy('student_name')
            ->limit($limit)
            ->get()
            ->map(fn (StudentProfile $p) => $this->snapshot($p))
            ->all();
    }

    public function findByRef(string $studentIdRef): ?StudentSnapshot
    {
        $profile = StudentProfile::query()
            ->where('student_id_ref', $studentIdRef)
            ->where('is_active', true)
            ->first();

        return $profile ? $this->snapshot($profile) : null;
    }

    private function snapshot(StudentProfile $profile): StudentSnapshot
    {
        return new StudentSnapshot(
            $profile->student_id_ref,
            $profile->student_name,
            $profile->grade,
            $profile->class_name,
        );
    }
}
