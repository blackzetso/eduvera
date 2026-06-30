<?php

namespace App\Modules\Canteen\Integration\Contracts;

use App\Models\User;
use App\Modules\Canteen\Integration\DTOs\FamilyContextSnapshot;
use App\Modules\Canteen\Integration\DTOs\GuardianSnapshot;

interface GuardianIntegrationPort
{
    public function resolvePrimaryGuardian(User $student): ?GuardianSnapshot;

    /**
     * @return list<GuardianSnapshot>
     */
    public function guardiansForStudent(User $student): array;

    public function familyContextForStudent(User $student): FamilyContextSnapshot;

    public function assertGuardianLinkedToStudent(User $guardian, User $student): void;

    /**
     * @return list<string>
     */
    public function studentRefsForGuardian(User $guardian): array;
}
