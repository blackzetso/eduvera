<?php

namespace App\Modules\Canteen\Services;

use App\Models\User;
use App\Modules\Canteen\Integration\Contracts\GuardianIntegrationPort;
use App\Modules\Canteen\Models\StudentProfile;

class CanteenGuardianProfileSyncService
{
    public function __construct(protected GuardianIntegrationPort $guardians) {}

    public function syncForStudent(User $student, ?StudentProfile $profile = null): StudentProfile
    {
        $profile ??= StudentProfile::query()
            ->where(fn ($q) => $q
                ->where('user_id', $student->id)
                ->orWhere('student_id_ref', (string) $student->id))
            ->first();

        if (! $profile) {
            throw new \InvalidArgumentException('Canteen student profile is required before guardian sync.');
        }

        $primary = $this->guardians->resolvePrimaryGuardian($student);
        $family = $this->guardians->familyContextForStudent($student);

        $metadata = $profile->metadata ?? [];
        $metadata['guardian_sync'] = [
            'synced_at' => now()->toIso8601String(),
            'guardians_count' => count($family->guardians),
            'siblings_count' => count($family->siblings),
        ];

        if ($primary) {
            $metadata['guardian_id_ref'] = $primary->guardianIdRef;
            $metadata['guardian_name'] = $primary->guardianName;
            $metadata['guardian_relationship_type'] = $primary->relationshipType;
            $metadata['guardian_is_financial_responsible'] = $primary->isFinancialResponsible;
        }

        $profile->update([
            'primary_guardian_user_id' => $primary ? (int) $primary->guardianIdRef : null,
            'guardian_id_ref' => $primary?->guardianIdRef,
            'metadata' => $metadata,
        ]);

        return $profile->fresh();
    }
}
