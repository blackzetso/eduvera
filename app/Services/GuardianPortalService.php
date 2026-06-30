<?php

namespace App\Services;

use App\Models\AttendanceAlert;
use App\Models\StudentBehaviorRecord;
use App\Models\StudentGrade;
use App\Models\TimetablePeriod;
use App\Services\DailyAbsenceCoverageService;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Support\Collection;

class GuardianPortalService
{
    public function __construct(
        protected AttendanceStatsService $attendanceStats,
    ) {}

    public function childrenForGuardian(User $guardian): Collection
    {
        return $guardian->students()
            ->with('category')
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.email', 'users.student_code', 'users.category_id', 'users.gender']);
    }

    public function childCardSummary(User $student): array
    {
        $attendance = $this->attendanceStats->studentSummary($student->id);
        $grades = StudentGrade::query()->where('student_id', $student->id)->get();
        $behaviors = StudentBehaviorRecord::query()->where('student_id', $student->id)->get();

        $avgPercent = $grades->isEmpty()
            ? null
            : round($grades->avg(fn ($g) => $g->percentage()), 1);

        $alert = AttendanceAlert::query()
            ->where('student_id', $student->id)
            ->whereNull('acknowledged_at')
            ->latest('triggered_at')
            ->first();

        return [
            'attendance' => [
                'present' => $attendance['present'],
                'absent' => $attendance['absent'],
                'late' => $attendance['late'],
                'excused' => $attendance['excused'],
            ],
            'grades_average' => $avgPercent,
            'grades_count' => $grades->count(),
            'behavior' => [
                'positive' => $behaviors->where('severity', 'positive')->count(),
                'neutral' => $behaviors->where('severity', 'neutral')->count(),
                'negative' => $behaviors->where('severity', 'negative')->count(),
            ],
            'attendance_alert' => $alert ? [
                'level' => $alert->level,
                'absences_count' => $alert->absences_count,
                'triggered_at' => $alert->triggered_at?->toDateString(),
            ] : null,
        ];
    }

    public function childSchedule(User $student, ?Carbon $forDate = null): Collection
    {
        if (! $student->category_id) {
            return collect();
        }

        $date = $forDate ?? today();
        $coverageService = app(DailyAbsenceCoverageService::class);
        $dayName = $coverageService->arabicDayNameForDate($date);
        $coverages = $coverageService
            ->approvedCoveragesForDate($date)
            ->keyBy('timetable_period_id');

        $adjustments = app(DailyLessonSwapService::class)
            ->activeAdjustmentsForDate($date);

        return TimetablePeriod::query()
            ->where('category_id', $student->category_id)
            ->with(['day', 'assignments' => fn ($q) => $q->where('type', 'main'), 'assignments.subject', 'assignments.teacher'])
            ->orderBy('timetable_day_id')
            ->orderBy('period_number')
            ->get()
            ->map(function ($period) use ($coverages, $adjustments, $date, $dayName) {
                $main = $period->assignments->first();
                $isTodaySlot = $date->isToday() && $period->day?->day_name === $dayName;
                $coverage = $isTodaySlot ? $coverages->get($period->id) : null;

                $resolved = app(DailyLessonSwapService::class)->resolvePeriodForStudent(
                    $period,
                    $date,
                    $coverages,
                    $adjustments,
                    $isTodaySlot
                );

                return [
                    'id' => $period->id,
                    'day_name' => $period->day?->day_name,
                    'period_number' => $period->period_number,
                    'time_from' => $period->time_from,
                    'time_to' => $period->time_to,
                    'subject' => $resolved['subject'] ?? $main?->subject?->name,
                    'teacher' => $resolved['teacher'] ?? $main?->teacher?->name,
                    'substitute_teacher' => $resolved['substitute_teacher'] ?? ($coverage ? $coverage->replacementTeacher?->name : null),
                    'display_teacher' => $resolved['display_teacher'] ?? $resolved['teacher'] ?? $main?->teacher?->name,
                    'is_coverage_today' => (bool) ($resolved['is_coverage_today'] ?? $coverage),
                    'is_temporary' => (bool) ($resolved['is_temporary'] ?? false),
                    'temporary_label' => $resolved['temporary_label'] ?? null,
                    'temporary_tooltip' => $resolved['temporary_tooltip'] ?? null,
                    'schedule_note' => $resolved['schedule_note'] ?? null,
                ];
            });
    }

    public function assertGuardianCanAccessChild(User $guardian, User $student): void
    {
        abort_unless($guardian->user_type === 'guardian', 403);
        abort_unless($student->user_type === 'student', 404);
        abort_unless(
            $guardian->students()->where('users.id', $student->id)->exists(),
            403,
            'هذا الطالب غير مرتبط بحسابك.'
        );
    }
}
