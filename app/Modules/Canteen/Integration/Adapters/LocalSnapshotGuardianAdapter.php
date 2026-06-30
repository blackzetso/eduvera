<?php

namespace App\Modules\Canteen\Integration\Adapters;

use App\Models\User;
use App\Modules\Canteen\Exceptions\GuardianAccessDeniedException;
use App\Modules\Canteen\Integration\Contracts\GuardianIntegrationPort;
use App\Modules\Canteen\Integration\DTOs\FamilyContextSnapshot;
use App\Modules\Canteen\Integration\DTOs\GuardianSnapshot;
use App\Modules\Canteen\Models\StudentProfile;

class LocalSnapshotGuardianAdapter implements GuardianIntegrationPort
{
    public function resolvePrimaryGuardian(User $student): ?GuardianSnapshot
    {
        $profile = $this->profileForStudent($student);

        if (! $profile) {
            return null;
        }

        $guardianIdRef = $profile->guardian_id_ref
            ?? $profile->metadata['guardian_id_ref'] ?? null;

        if (! $guardianIdRef) {
            return null;
        }

        return new GuardianSnapshot(
            (string) $guardianIdRef,
            $profile->metadata['guardian_name'] ?? 'Guardian',
            $profile->metadata['guardian_relationship_type'] ?? null,
            true,
            (bool) ($profile->metadata['guardian_is_financial_responsible'] ?? false),
        );
    }

    public function guardiansForStudent(User $student): array
    {
        $primary = $this->resolvePrimaryGuardian($student);

        return $primary ? [$primary] : [];
    }

    public function familyContextForStudent(User $student): FamilyContextSnapshot
    {
        $primary = $this->resolvePrimaryGuardian($student);

        return new FamilyContextSnapshot(
            (string) $student->id,
            $primary,
            $primary ? [$primary] : [],
            [],
        );
    }

    public function assertGuardianLinkedToStudent(User $guardian, User $student): void
    {
        $primary = $this->resolvePrimaryGuardian($student);

        if (! $primary || $primary->guardianIdRef !== (string) $guardian->id) {
            throw new GuardianAccessDeniedException('Guardian is not linked to this student in local snapshot.');
        }
    }

    public function studentRefsForGuardian(User $guardian): array
    {
        return StudentProfile::query()
            ->where(fn ($q) => $q
                ->where('guardian_id_ref', (string) $guardian->id)
                ->orWhere('metadata->guardian_id_ref', (string) $guardian->id))
            ->orderBy('student_name')
            ->pluck('student_id_ref')
            ->values()
            ->all();
    }

    protected function profileForStudent(User $student): ?StudentProfile
    {
        return StudentProfile::query()
            ->where(fn ($q) => $q
                ->where('user_id', $student->id)
                ->orWhere('student_id_ref', (string) $student->id))
            ->first();
    }
}
