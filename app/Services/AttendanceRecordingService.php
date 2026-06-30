<?php

namespace App\Services;

use App\Models\StudentAttendance;
use App\Models\TimetableAssignment;
use App\Models\TimetablePeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AttendanceRecordingService
{
    public function __construct(
        protected AttendanceAuditService $auditService,
        protected AttendanceStatsService $statsService,
        protected AttendanceThresholdService $thresholdService,
    ) {}

    /**
     * @param  array<int, array{student_id: int, status: string, notes?: string, arrival_time?: string}>  $marks
     */
    public function markClassAttendance(
        TimetablePeriod $period,
        string $date,
        array $marks,
        int $recordedBy,
    ): int {
        $assignment = $period->assignments()->first();
        $subjectId = $assignment?->subject_id;

        $count = 0;

        DB::transaction(function () use ($period, $date, $marks, $recordedBy, $subjectId, &$count) {
            foreach ($marks as $mark) {
                $student = User::find($mark['student_id']);
                if (! $student || $student->user_type !== 'student') {
                    continue;
                }

                $old = StudentAttendance::query()
                    ->where('student_id', $mark['student_id'])
                    ->where('attendance_date', $date)
                    ->where('session_type', 'class')
                    ->where('timetable_period_id', $period->id)
                    ->first();

                $attendance = StudentAttendance::updateOrCreate(
                    [
                        'student_id' => $mark['student_id'],
                        'attendance_date' => $date,
                        'session_type' => 'class',
                        'timetable_period_id' => $period->id,
                    ],
                    [
                        'category_id' => $student->category_id,
                        'subject_id' => $subjectId,
                        'period_number' => $period->period_number,
                        'status' => $mark['status'],
                        'notes' => $mark['notes'] ?? null,
                        'arrival_time' => $mark['arrival_time'] ?? null,
                        'source' => 'manual',
                        'recorded_by' => $recordedBy,
                    ]
                );

                if ($old) {
                    $this->auditService->logUpdated($attendance, $old->only(['status', 'notes', 'arrival_time']));
                } else {
                    $this->auditService->logCreated($attendance);
                }

                $count++;
            }
        });

        $this->statsService->invalidateTodayCache($period->category_id);
        $this->thresholdService->checkAllStudents();

        return $count;
    }

    public function teacherCanMarkPeriod(int $teacherId, TimetablePeriod $period): bool
    {
        return TimetableAssignment::query()
            ->where('timetable_period_id', $period->id)
            ->where('teacher_id', $teacherId)
            ->exists();
    }
}
