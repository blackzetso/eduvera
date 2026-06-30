<?php

namespace App\Services\Admission;

use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionAssignmentHistory;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdmissionAssignmentService
{
    public function assign(
        AdmissionApplication $application,
        ?int $officerUserId,
        ?string $notes = null,
        ?int $performedByUserId = null,
    ): AdmissionApplication {
        return DB::transaction(function () use ($application, $officerUserId, $notes, $performedByUserId) {
            $fromUserId = $application->assigned_to_user_id;

            if ($officerUserId) {
                User::query()
                    ->where('id', $officerUserId)
                    ->where('user_type', 'admin')
                    ->firstOrFail();
            }

            $application->forceFill(['assigned_to_user_id' => $officerUserId])->save();

            AdmissionAssignmentHistory::create([
                'admission_application_id' => $application->id,
                'from_user_id' => $fromUserId,
                'to_user_id' => $officerUserId,
                'notes' => $notes,
                'performed_by_user_id' => $performedByUserId ?? Auth::id(),
                'effective_at' => now(),
            ]);

            return $application->fresh(['assignedTo']);
        });
    }
}
