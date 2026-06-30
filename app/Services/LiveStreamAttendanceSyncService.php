<?php

namespace App\Services;

use App\Models\LiveStreamAttendance;
use App\Models\StudentAttendance;
use App\Models\User;

class LiveStreamAttendanceSyncService
{
    public function __construct(
        protected AttendanceAuditService $auditService,
    ) {}

    public function resolveStudentId(LiveStreamAttendance $record): ?int
    {
        if ($record->student_id) {
            return $record->student_id;
        }

        if ($record->student_email) {
            $student = User::query()
                ->where('user_type', 'student')
                ->where('email', $record->student_email)
                ->first();

            if ($student) {
                $record->update(['student_id' => $student->id]);

                return $student->id;
            }
        }

        return null;
    }

    public function syncRecord(LiveStreamAttendance $record): ?StudentAttendance
    {
        $studentId = $this->resolveStudentId($record);
        if (! $studentId) {
            return null;
        }

        $student = User::find($studentId);
        if (! $student || ! $student->category_id) {
            return null;
        }

        $date = $record->join_time?->toDateString() ?? now()->toDateString();
        $status = ($record->duration_seconds ?? 0) > 60 ? 'present' : 'absent';

        $attendance = StudentAttendance::updateOrCreate(
            [
                'student_id' => $studentId,
                'attendance_date' => $date,
                'session_type' => 'live_stream',
                'live_stream_id' => $record->live_stream_id,
                'timetable_period_id' => null,
            ],
            [
                'category_id' => $student->category_id,
                'status' => $status,
                'session_label' => 'بث مباشر',
                'source' => 'live_stream',
                'metadata_json' => [
                    'live_stream_attendance_id' => $record->id,
                    'duration_seconds' => $record->duration_seconds,
                ],
            ]
        );

        if ($attendance->wasRecentlyCreated) {
            $this->auditService->logCreated($attendance);
        }

        return $attendance;
    }

    public function syncAll(?int $liveStreamId = null): int
    {
        $count = 0;

        $query = LiveStreamAttendance::query();
        if ($liveStreamId) {
            $query->where('live_stream_id', $liveStreamId);
        }

        $query->chunkById(100, function ($records) use (&$count) {
            foreach ($records as $record) {
                if ($this->syncRecord($record)) {
                    $count++;
                }
            }
        });

        return $count;
    }
}
