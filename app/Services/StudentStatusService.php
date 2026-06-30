<?php

namespace App\Services;

use App\Models\StudentStatusHistory;
use App\Models\User;
use App\Support\Student\StudentStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StudentStatusService
{
    public function recordInitial(User $student): void
    {
        if ($student->user_type !== 'student') {
            return;
        }

        StudentStatusHistory::create([
            'student_id' => $student->id,
            'from_status' => null,
            'to_status' => $student->student_status ?? StudentStatus::ACTIVE,
            'changed_by_user_id' => Auth::id(),
            'effective_at' => now(),
        ]);
    }

    public function transition(
        User $student,
        string $toStatus,
        ?string $reason = null,
        ?string $notes = null,
        ?\DateTimeInterface $effectiveAt = null,
        bool $validate = true,
    ): void {
        if ($student->user_type !== 'student') {
            return;
        }

        $fromStatus = $student->student_status ?? StudentStatus::ACTIVE;

        if ($fromStatus === $toStatus) {
            return;
        }

        if ($validate && ! StudentStatus::canTransition($fromStatus, $toStatus)) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن الانتقال من «' . StudentStatus::label($fromStatus) . '» إلى «' . StudentStatus::label($toStatus) . '».',
            ]);
        }

        $student->update(['student_status' => $toStatus]);

        StudentStatusHistory::create([
            'student_id' => $student->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'notes' => $notes,
            'changed_by_user_id' => Auth::id(),
            'effective_at' => $effectiveAt ?? now(),
        ]);
    }
}
