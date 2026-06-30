<?php

namespace App\Modules\Canteen\Integration\Adapters;

use App\Models\User;
use App\Modules\Canteen\Exceptions\GuardianAccessDeniedException;
use App\Modules\Canteen\Integration\Contracts\GuardianIntegrationPort;
use App\Modules\Canteen\Integration\DTOs\FamilyContextSnapshot;
use App\Modules\Canteen\Integration\DTOs\GuardianSnapshot;
use App\Modules\Canteen\Integration\DTOs\GuardianStudentLinkSnapshot;

class CoreGuardianIntegrationAdapter implements GuardianIntegrationPort
{
    public function resolvePrimaryGuardian(User $student): ?GuardianSnapshot
    {
        $guardians = $this->loadGuardians($student);

        if ($guardians->isEmpty()) {
            return null;
        }

        $primary = $guardians->first(fn (User $g) => (bool) $g->pivot?->is_primary);

        if (! $primary) {
            $primary = $guardians->first(fn (User $g) => (bool) $g->pivot?->is_financial_responsible);
        }

        $primary ??= $guardians->first();

        return $this->guardianSnapshot($primary);
    }

    public function guardiansForStudent(User $student): array
    {
        return $this->loadGuardians($student)
            ->map(fn (User $guardian) => $this->guardianSnapshot($guardian))
            ->values()
            ->all();
    }

    public function familyContextForStudent(User $student): FamilyContextSnapshot
    {
        $guardians = $this->guardiansForStudent($student);
        $primary = $this->resolvePrimaryGuardian($student);
        $siblings = $this->siblingsForStudent($student);

        return new FamilyContextSnapshot(
            (string) $student->id,
            $primary,
            $guardians,
            $siblings,
        );
    }

    public function assertGuardianLinkedToStudent(User $guardian, User $student): void
    {
        if ($guardian->user_type !== 'guardian') {
            throw new GuardianAccessDeniedException('User is not a guardian.');
        }

        if ($student->user_type !== 'student') {
            throw new GuardianAccessDeniedException('Target user is not a student.');
        }

        $linked = $student->guardians()->where('users.id', $guardian->id)->exists();

        if (! $linked) {
            throw new GuardianAccessDeniedException('Guardian is not linked to this student.');
        }
    }

    public function studentRefsForGuardian(User $guardian): array
    {
        if ($guardian->user_type !== 'guardian') {
            return [];
        }

        return $guardian->students()
            ->orderBy('name')
            ->pluck('users.id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<GuardianStudentLinkSnapshot>
     */
    protected function siblingsForStudent(User $student): array
    {
        $guardianIds = $student->guardians()->pluck('users.id');

        if ($guardianIds->isEmpty()) {
            return [];
        }

        return User::query()
            ->students()
            ->whereKeyNot($student->id)
            ->whereHas('guardians', fn ($q) => $q->whereIn('guardian_student.guardian_id', $guardianIds))
            ->orderBy('name')
            ->get(['users.id', 'users.name'])
            ->map(fn (User $sibling) => new GuardianStudentLinkSnapshot(
                (string) $sibling->id,
                $sibling->name,
            ))
            ->values()
            ->all();
    }

    protected function loadGuardians(User $student): \Illuminate\Support\Collection
    {
        return $student->guardians()
            ->orderByDesc('guardian_student.is_primary')
            ->orderByDesc('guardian_student.is_financial_responsible')
            ->orderBy('users.name')
            ->get();
    }

    protected function guardianSnapshot(User $guardian): GuardianSnapshot
    {
        return new GuardianSnapshot(
            (string) $guardian->id,
            $guardian->name,
            $guardian->pivot?->relationship_type,
            (bool) $guardian->pivot?->is_primary,
            (bool) $guardian->pivot?->is_financial_responsible,
        );
    }
}
