<?php

namespace App\Services;

use App\Models\AttendanceAuditLog;
use App\Models\StudentAttendance;
use Illuminate\Http\Request;

class AttendanceAuditService
{
    public function log(
        StudentAttendance $attendance,
        string $event,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?int $actorId = null,
    ): void {
        AttendanceAuditLog::create([
            'attendance_id' => $attendance->id,
            'actor_id' => $actorId ?? auth()->id(),
            'event' => $event,
            'old_values_json' => $oldValues,
            'new_values_json' => $newValues,
            'reason' => $reason,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    public function logCreated(StudentAttendance $attendance): void
    {
        $this->log($attendance, 'created', null, $attendance->only([
            'student_id', 'status', 'attendance_date', 'session_type', 'source',
        ]));
    }

    public function logUpdated(StudentAttendance $attendance, array $oldValues): void
    {
        $event = isset($oldValues['status']) && $oldValues['status'] !== $attendance->status
            ? 'status_changed'
            : 'updated';

        $this->log($attendance, $event, $oldValues, $attendance->only(array_keys($oldValues)));
    }
}
