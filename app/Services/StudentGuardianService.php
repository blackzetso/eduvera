<?php

namespace App\Services;

use App\Models\User;
use App\Support\Student\GuardianRelationship;
use Illuminate\Validation\ValidationException;

class StudentGuardianService
{
    public function sync(User $student, array $guardianLinks): void
    {
        $this->assertSinglePrimary($guardianLinks);

        $guardianIds = collect($guardianLinks)->pluck('guardian_id')->map(fn ($id) => (int) $id)->all();

        $validGuardianIds = User::where('user_type', 'guardian')
            ->whereIn('id', $guardianIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $syncPayload = [];
        $primaryAssigned = false;

        foreach ($guardianLinks as $link) {
            $guardianId = (int) ($link['guardian_id'] ?? 0);
            if (! in_array($guardianId, $validGuardianIds, true)) {
                continue;
            }

            $isPrimary = (bool) ($link['is_primary'] ?? false);
            if ($isPrimary && $primaryAssigned) {
                $isPrimary = false;
            }
            if ($isPrimary) {
                $primaryAssigned = true;
            }

            $syncPayload[$guardianId] = [
                'relationship_type' => $link['relationship_type'] ?? GuardianRelationship::GUARDIAN,
                'is_primary' => $isPrimary,
                'is_emergency_contact' => (bool) ($link['is_emergency_contact'] ?? false),
                'is_pickup_authorized' => (bool) ($link['is_pickup_authorized'] ?? true),
                'is_financial_responsible' => (bool) ($link['is_financial_responsible'] ?? false),
            ];
        }

        if (! $primaryAssigned && ! empty($syncPayload)) {
            $firstKey = array_key_first($syncPayload);
            $syncPayload[$firstKey]['is_primary'] = true;
        }

        $student->guardians()->sync($syncPayload);
    }

    public function assertSinglePrimary(array $guardianLinks): void
    {
        $primaryCount = collect($guardianLinks)->where('is_primary', true)->count();

        if ($primaryCount > 1) {
            throw ValidationException::withMessages([
                'guardian_links' => 'يمكن تعيين ولي أمر أساسي واحد فقط.',
            ]);
        }
    }
}
