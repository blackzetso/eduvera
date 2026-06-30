<?php

namespace App\Services;

use App\Models\Category;
use App\Models\TeacherAttendance;
use App\Models\Timetable;
use App\Models\TimetableAssignment;
use App\Models\DailyTimetableAdjustment;
use App\Models\TimetableDailyCoverage;
use App\Models\TimetableDay;
use App\Models\TimetablePeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DailyAbsenceCoverageService
{
    protected function swap(): DailyLessonSwapService
    {
        return app(DailyLessonSwapService::class);
    }

    protected function prioritySettings(): CoveragePrioritySettingsService
    {
        return app(CoveragePrioritySettingsService::class);
    }

    public function arabicDayNameForDate(Carbon $date): string
    {
        $map = config('attendance.arabic_weekdays', []);

        return $map[$date->englishDayOfWeek] ?? $date->format('l');
    }

    public function getTimetable(): Timetable
    {
        $timetable = Timetable::query()
            ->withCount('periods')
            ->orderByDesc('periods_count')
            ->orderByDesc('id')
            ->first();

        if ($timetable) {
            return $timetable;
        }

        return Timetable::create([
            'name' => 'الجدول الدراسي',
            'academic_year' => date('Y') . '-' . (date('Y') + 1),
            'status' => 'active',
        ]);
    }

    /**
     * @return Collection<int, array>
     */
    public function absentTeachersForDate(Carbon $date): Collection
    {
        $statuses = config('attendance.teacher_unavailable_statuses', []);
        $labels = config('attendance.teacher_attendance_statuses', []);

        return TeacherAttendance::query()
            ->unavailableOnDate($date)
            ->with(['teacher.subjects:id,name'])
            ->get()
            ->map(function (TeacherAttendance $record) use ($labels) {
                $teacher = $record->teacher;
                $subjects = $teacher?->subjects?->pluck('name')->join('، ') ?: '—';

                return [
                    'teacher_id' => $teacher->id,
                    'name' => $teacher->name,
                    'department' => $teacher->department,
                    'job_title' => $teacher->job_title,
                    'specialization' => $subjects,
                    'status' => $record->status,
                    'status_label' => $labels[$record->status] ?? $record->status,
                    'reason' => $record->reason,
                    'subject_ids' => $teacher->subjects->pluck('id')->all(),
                ];
            });
    }

    /**
     * @return array{preview: array, date: string, day_name: string}
     */
    public function buildPreview(?string $date = null): array
    {
        $date = Carbon::parse($date ?? today());
        $timetable = $this->getTimetable();
        $dayName = $this->arabicDayNameForDate($date);

        $day = TimetableDay::query()
            ->where('timetable_id', $timetable->id)
            ->where('day_name', $dayName)
            ->first();

        $absentTeachers = $this->absentTeachersForDate($date);
        $absentIds = $absentTeachers->pluck('teacher_id')->all();

        $teachers = User::where('user_type', 'teacher')
            ->with(['subjects:id,name'])
            ->get(['id', 'name', 'email', 'department', 'job_title']);

        $coverageBalances = $this->coverageBalances($timetable);
        $weeklyWorkload = $this->teacherWeeklyWorkload($timetable);
        $weekCounts = $this->replacementCountsSinceWeekStart($date);
        $draftMeta = $this->draftMetaForDate($timetable, $date->toDateString());
        $todayCoverages = $this->todayCoveragesMap($date, $timetable->id);
        $todayAdjustments = $this->swap()->activeAdjustmentsForDate($date, $timetable->id);
        $adjustmentsByTrigger = $todayAdjustments->groupBy('trigger_period_id');

        $affected = collect();
        $dayPeriods = collect();
        if ($day && $absentIds) {
            $dayPeriods = TimetablePeriod::query()
                ->where('timetable_day_id', $day->id)
                ->where('timetable_id', $timetable->id)
                ->where('period_number', '>', 0)
                ->with([
                    'category:id,name',
                    'assignments' => fn ($q) => $q->where('type', 'main'),
                    'assignments.teacher:id,name',
                    'assignments.subject:id,name',
                ])
                ->orderBy('period_number')
                ->get();

            foreach ($dayPeriods as $period) {
                $main = $period->assignments->first();
                if (!$main || !in_array($main->teacher_id, $absentIds, true)) {
                    continue;
                }

                $absent = $absentTeachers->firstWhere('teacher_id', $main->teacher_id);
                $existing = $todayCoverages->get($period->id);

                $suggestion = $existing
                    ? $this->formatExistingSuggestion($existing, $teachers, $coverageBalances, $weekCounts)
                    : $this->suggestReplacement(
                        $period,
                        $main,
                        $absent,
                        $teachers,
                        $absentIds,
                        $dayPeriods,
                        $date,
                        $coverageBalances,
                        $timetable,
                        $weekCounts,
                        $weeklyWorkload
                    );

                $periodAdjustments = $adjustmentsByTrigger->get($period->id, collect());
                $activeAdj = $periodAdjustments->first(fn ($a) => in_array($a->status, ['draft', 'approved'], true));

                $affected->push([
                    'period_id' => $period->id,
                    'period_number' => $period->period_number,
                    'day_name' => $dayName,
                    'time_from' => substr((string) $period->time_from, 0, 5),
                    'time_to' => substr((string) $period->time_to, 0, 5),
                    'class_name' => $period->category?->name ?? '—',
                    'subject_id' => $main->subject_id,
                    'subject_name' => $main->subject?->name ?? '—',
                    'absent_teacher_id' => $main->teacher_id,
                    'absent_teacher_name' => $main->teacher?->name,
                    'status' => $this->lessonResolutionStatus($existing, $activeAdj),
                    'resolution' => $this->lessonResolutionType($existing, $activeAdj),
                    'adjustment' => $activeAdj ? $this->formatAdjustment($activeAdj) : null,
                    'suggestion' => $suggestion,
                    'available_teachers' => $suggestion['available_teachers'] ?? [],
                    'busy_teachers' => $suggestion['busy_teachers'] ?? [],
                    'is_temporary' => (bool) $activeAdj,
                    'temporary_label' => $activeAdj ? 'مؤقت' : null,
                    'temporary_tooltip' => 'تعديل يومي لا يؤثر على الجدول الأساسي',
                    'is_draft' => $existing && $existing->status === 'draft',
                ]);
            }
        }

        $deptPlanIds = $this->departmentPlanTeacherIds($timetable);
        $teacherCoverageHistory = $this->buildTeacherCoverageHistoryMap(
            (int) $timetable->id,
            $teachers,
            $date,
            $coverageBalances
        );
        $coverageReport = $this->buildCoverageReport($affected, $teachers, $coverageBalances);
        $coverageReport['department_insights'] = $this->buildDepartmentInsights(
            $affected,
            $timetable,
            $deptPlanIds
        );

        $absentTeachers = $absentTeachers->map(function (array $absent) use ($affected, $day, $dayPeriods) {
            $count = $affected->where('absent_teacher_id', $absent['teacher_id'])->count();
            if ($count === 0 && $day) {
                $count = TimetableAssignment::query()
                    ->where('type', 'main')
                    ->where('teacher_id', $absent['teacher_id'])
                    ->whereHas('period', fn ($q) => $q->where('timetable_day_id', $day->id)->where('period_number', '>', 0))
                    ->count();
            }
            $absent['affected_count'] = $count;

            return $absent;
        });

        $teacherSchedules = ($day && $absentTeachers->isNotEmpty())
            ? $this->buildTeacherSchedules($absentTeachers, $dayPeriods, $affected, (int) $day->id)
            : [];

        $coverageRoster = $this->buildCoverageRoster($teachers, $coverageBalances, $weekCounts, $weeklyWorkload);
        $teacherStats = $coverageRoster;

        return [
            'date' => $date->toDateString(),
            'day_name' => $dayName,
            'has_school_day' => (bool) $day,
            'absent_teachers' => $absentTeachers->values()->all(),
            'affected_lessons' => $affected->values()->all(),
            'coverage_plan' => $this->buildCoveragePlan($affected, $todayCoverages, $todayAdjustments),
            'daily_adjustments' => $todayAdjustments->map(fn ($a) => $this->formatAdjustment($a))->values()->all(),
            'teacher_stats' => $teacherStats,
            'coverage_roster' => $coverageRoster,
            'summary' => [
                'absent_count' => $absentTeachers->count(),
                'affected_count' => $affected->count(),
                'approved_count' => $affected->where('status', 'approved')->count(),
                'swap_count' => $todayAdjustments->where('swap_type', '!=', 'replace_teacher')->count(),
                'pending_count' => $affected->where('status', 'needs_coverage')->count(),
                'draft_count' => $affected->where('is_draft', true)->count(),
                'completion_percent' => $coverageReport['completion_percent'],
            ],
            'coverage_balances' => $coverageBalances,
            'coverage_priority' => $this->prioritySettings()->forPreview(),
            'teacher_schedules' => $teacherSchedules,
            'coverage_report' => $coverageReport,
            'department_plan_teacher_ids' => $deptPlanIds,
            'teacher_coverage_history' => $teacherCoverageHistory,
            'teacher_weekly_workload' => $weeklyWorkload,
            'coverage_draft_meta' => $draftMeta,
            'has_saved_draft' => (bool) $draftMeta,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $assignments
     * @param  array<string, mixed>  $wizardState
     */
    public function saveCoverageDraft(string $date, array $assignments, array $wizardState = [], ?int $userId = null): array
    {
        $dateCarbon = Carbon::parse($date);
        $timetable = $this->getTimetable();

        DB::transaction(function () use ($dateCarbon, $timetable, $assignments, $wizardState, $userId) {
            TimetableDailyCoverage::query()
                ->forDate($dateCarbon)
                ->where('timetable_id', $timetable->id)
                ->where('status', 'draft')
                ->delete();

            foreach ($assignments as $row) {
                if (empty($row['replacement_teacher_id'])) {
                    continue;
                }

                $period = TimetablePeriod::with('assignments')->findOrFail($row['period_id']);
                $main = $period->assignments->where('type', 'main')->first();

                TimetableDailyCoverage::create([
                    'coverage_date' => $dateCarbon->toDateString(),
                    'timetable_id' => $timetable->id,
                    'timetable_period_id' => $period->id,
                    'absent_teacher_id' => $row['absent_teacher_id'] ?? $main?->teacher_id,
                    'replacement_teacher_id' => $row['replacement_teacher_id'],
                    'subject_id' => $main?->subject_id,
                    'category_id' => $period->category_id,
                    'reason' => $row['reason'] ?? null,
                    'match_score' => (int) ($row['match_score'] ?? 0),
                    'match_reasons' => $row['match_reasons'] ?? [],
                    'status' => 'draft',
                    'created_by' => $userId,
                ]);
            }

            $settings = $timetable->settings ?? [];
            $meta = $settings['coverage_draft_meta'] ?? [];
            $meta[$dateCarbon->toDateString()] = [
                'saved_at' => now()->toIso8601String(),
                'saved_at_label' => now()->format('Y-m-d H:i'),
                'selected_teacher_id' => isset($wizardState['selected_teacher_id'])
                    ? (int) $wizardState['selected_teacher_id']
                    : null,
                'selected_period_id' => isset($wizardState['selected_period_id'])
                    ? (int) $wizardState['selected_period_id']
                    : null,
                'wizard_step' => $wizardState['wizard_step'] ?? 'overview',
                'saved_by' => $userId,
            ];
            $settings['coverage_draft_meta'] = $meta;
            $timetable->update(['settings' => $settings]);
        });

        return [
            'success' => true,
            'message' => 'تم حفظ مسودة خطة التغطية',
            'preview' => $this->buildPreview($date),
        ];
    }

    public function approveCoverage(string $date, array $assignments, ?int $userId = null): array
    {
        $dateCarbon = Carbon::parse($date);
        $timetable = $this->getTimetable();

        $assignments = $assignments ?: [];

        DB::transaction(function () use ($dateCarbon, $timetable, $assignments, $userId) {
            if ($assignments !== []) {
                TimetableDailyCoverage::query()
                    ->forDate($dateCarbon)
                    ->where('timetable_id', $timetable->id)
                    ->whereIn('status', ['draft', 'approved'])
                    ->delete();
            }

            foreach ($assignments as $row) {
                $period = TimetablePeriod::with('assignments')->findOrFail($row['period_id']);
                $main = $period->assignments->where('type', 'main')->first();

                TimetableDailyCoverage::create([
                    'coverage_date' => $dateCarbon->toDateString(),
                    'timetable_id' => $timetable->id,
                    'timetable_period_id' => $period->id,
                    'absent_teacher_id' => $row['absent_teacher_id'] ?? $main?->teacher_id,
                    'replacement_teacher_id' => $row['replacement_teacher_id'],
                    'subject_id' => $main?->subject_id,
                    'category_id' => $period->category_id,
                    'reason' => $row['reason'] ?? null,
                    'match_score' => (int) ($row['match_score'] ?? 0),
                    'match_reasons' => $row['match_reasons'] ?? [],
                    'status' => 'approved',
                    'created_by' => $userId,
                ]);
            }

            $settings = $timetable->settings ?? [];
            $settings['active_daily_coverage'] = [
                'date' => $dateCarbon->toDateString(),
                'approved_at' => now()->toIso8601String(),
                'count' => count($assignments),
            ];
            if (isset($settings['coverage_draft_meta'][$dateCarbon->toDateString()])) {
                unset($settings['coverage_draft_meta'][$dateCarbon->toDateString()]);
            }
            $timetable->update(['settings' => $settings]);
        });

        $notified = 0;
        if ($assignments !== []) {
            $notified = $this->sendSubstituteTeacherNotifications(
                $dateCarbon->toDateString(),
                $assignments,
                'approved'
            );
        }

        return [
            'success' => true,
            'message' => 'تم اعتماد خطة تغطية الغياب بنجاح',
            'distribution_report' => $this->buildSubstituteDistributionReport($dateCarbon->toDateString()),
            'notifications_queued' => $notified,
        ];
    }

    /**
     * إشعار معلم بديل فور تعيينه (مسودة — قبل اعتماد اليوم).
     *
     * @param  array<string, mixed>  $row
     */
    public function notifySubstituteAssignment(string $date, array $row): bool
    {
        $replacementId = (int) ($row['replacement_teacher_id'] ?? 0);
        if ($replacementId < 1) {
            return false;
        }

        $sent = $this->sendSubstituteTeacherNotifications($date, [$row], 'draft');

        return $sent > 0;
    }

    /**
     * تقرير توزيع الاحتياط لليوم (من قاعدة البيانات أو من قائمة تعيينات مؤقتة).
     *
     * @param  array<int, array<string, mixed>>|null  $assignmentRows
     * @return array<string, mixed>
     */
    public function buildSubstituteDistributionReport(string $date, ?array $assignmentRows = null): array
    {
        $dateCarbon = Carbon::parse($date);
        $timetable = $this->getTimetable();
        $dayName = $this->arabicDayNameForDate($dateCarbon);
        $teachers = User::query()->where('user_type', 'teacher')->get(['id', 'name']);
        $coverageBalances = $this->coverageBalances($timetable);

        $assignments = [];

        if ($assignmentRows !== null) {
            foreach ($assignmentRows as $row) {
                $replacementId = (int) ($row['replacement_teacher_id'] ?? 0);
                if ($replacementId < 1) {
                    continue;
                }
                $period = TimetablePeriod::with(['category', 'assignments.teacher', 'assignments.subject'])->find($row['period_id'] ?? 0);
                if (! $period) {
                    continue;
                }
                $main = $period->assignments->where('type', 'main')->first();
                $replacement = $teachers->firstWhere('id', $replacementId);
                $absentId = (int) ($row['absent_teacher_id'] ?? $main?->teacher_id ?? 0);
                $absent = $teachers->firstWhere('id', $absentId);

                $assignments[] = $this->formatSubstituteReportRow(
                    $period,
                    $main,
                    $absent,
                    $replacement,
                    $row['status'] ?? 'pending'
                );
            }
        } else {
            $coverages = TimetableDailyCoverage::query()
                ->forDate($dateCarbon)
                ->where('timetable_id', $timetable->id)
                ->whereIn('status', ['draft', 'approved'])
                ->with(['period.category', 'period.assignments.teacher', 'period.assignments.subject', 'absentTeacher:id,name', 'replacementTeacher:id,name', 'subject:id,name'])
                ->orderBy('timetable_period_id')
                ->get();

            foreach ($coverages as $coverage) {
                $period = $coverage->period;
                if (! $period) {
                    continue;
                }
                $main = $period->assignments->where('type', 'main')->first();
                $assignments[] = $this->formatSubstituteReportRow(
                    $period,
                    $main,
                    $coverage->absentTeacher,
                    $coverage->replacementTeacher,
                    $coverage->status
                );
            }
        }

        usort($assignments, fn ($a, $b) => ($a['period_number'] ?? 0) <=> ($b['period_number'] ?? 0));

        $loads = [];
        foreach ($assignments as $line) {
            $tid = (int) ($line['replacement_teacher_id'] ?? 0);
            if ($tid) {
                $loads[$tid] = ($loads[$tid] ?? 0) + 1;
            }
        }

        $distribution = [];
        foreach ($loads as $teacherId => $count) {
            $teacher = $teachers->firstWhere('id', $teacherId);
            $balance = (int) ($coverageBalances[$teacherId]['total'] ?? 0);
            $lines = array_values(array_filter(
                $assignments,
                fn ($a) => (int) ($a['replacement_teacher_id'] ?? 0) === $teacherId
            ));
            $distribution[] = [
                'teacher_id' => $teacherId,
                'teacher_name' => $teacher?->name ?? '—',
                'coverage_count' => $count,
                'balance_label' => '+'.$balance,
                'coverage_balance' => $balance,
                'periods' => array_map(fn ($l) => [
                    'period_number' => $l['period_number'],
                    'subject_name' => $l['subject_name'],
                    'class_name' => $l['class_name'],
                    'time' => $l['time'],
                ], $lines),
            ];
        }

        usort($distribution, fn ($a, $b) => $b['coverage_count'] <=> $a['coverage_count']);

        $total = count($assignments);
        $uniqueSubstitutes = count($distribution);

        return [
            'date' => $dateCarbon->toDateString(),
            'day_name' => $dayName,
            'summary' => [
                'assignments_total' => $total,
                'substitute_teachers_count' => $uniqueSubstitutes,
                'distribution' => $distribution,
                'most_loaded' => $distribution[0] ?? null,
                'least_loaded' => $distribution ? $distribution[count($distribution) - 1] : null,
            ],
            'assignments' => $assignments,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $assignmentRows
     */
    public function sendSubstituteTeacherNotifications(string $date, array $assignmentRows, string $status = 'approved'): int
    {
        $dateCarbon = Carbon::parse($date);
        $dayName = $this->arabicDayNameForDate($dateCarbon);
        $grouped = [];

        foreach ($assignmentRows as $row) {
            $replacementId = (int) ($row['replacement_teacher_id'] ?? 0);
            if ($replacementId < 1) {
                continue;
            }
            $grouped[$replacementId][] = $row;
        }

        $sent = 0;

        foreach ($grouped as $teacherId => $rows) {
            $teacher = User::query()->where('id', $teacherId)->where('user_type', 'teacher')->first();
            if (! $teacher) {
                Log::warning('Daily coverage: substitute teacher not found for notification', ['teacher_id' => $teacherId]);

                continue;
            }

            $periods = [];
            foreach ($rows as $row) {
                $period = TimetablePeriod::with(['category', 'assignments.teacher', 'assignments.subject'])
                    ->find($row['period_id'] ?? 0);
                if (! $period) {
                    continue;
                }
                $main = $period->assignments->where('type', 'main')->first();
                $absentId = (int) ($row['absent_teacher_id'] ?? $main?->teacher_id ?? 0);
                $absent = User::find($absentId);
                $periods[] = $this->formatSubstituteReportRow($period, $main, $absent, $teacher, $status);
            }

            if ($periods === []) {
                continue;
            }

            $teacher->notify(new \App\Notifications\SubstituteCoverageAssignmentNotification(
                $dateCarbon->toDateString(),
                $dayName,
                $periods,
                $status
            ));

            $sent++;
        }

        Log::info('Daily coverage: substitute teacher notifications sent', [
            'date' => $date,
            'status' => $status,
            'teachers_notified' => $sent,
        ]);

        return $sent;
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatSubstituteReportRow(
        TimetablePeriod $period,
        ?TimetableAssignment $main,
        ?User $absent,
        ?User $replacement,
        string $status = 'pending'
    ): array {
        return [
            'period_id' => $period->id,
            'period_number' => $period->period_number,
            'time' => substr((string) $period->time_from, 0, 5).' - '.substr((string) $period->time_to, 0, 5),
            'subject_name' => $main?->subject?->name ?? '—',
            'class_name' => $period->category?->name ?? '—',
            'absent_teacher_id' => $absent?->id,
            'absent_teacher_name' => $absent?->name ?? '—',
            'replacement_teacher_id' => $replacement?->id,
            'replacement_teacher_name' => $replacement?->name ?? '—',
            'status' => $status,
            'status_label' => $status === 'approved' ? 'معتمد' : ($status === 'draft' ? 'مسودة' : 'قيد التوزيع'),
        ];
    }

    public function markTeacherAbsent(int $teacherId, string $date, string $status, ?string $reason, ?int $recordedBy): TeacherAttendance
    {
        if (!in_array($status, array_keys(config('attendance.teacher_attendance_statuses', [])), true)) {
            abort(422, 'حالة غير صالحة');
        }

        return TeacherAttendance::updateOrCreate(
            [
                'teacher_id' => $teacherId,
                'attendance_date' => $date,
            ],
            [
                'status' => $status,
                'reason' => $reason,
                'source' => 'manual',
                'recorded_by' => $recordedBy,
            ]
        );
    }

    public function closeDay(?string $date = null): array
    {
        $dateCarbon = Carbon::parse($date ?? today());
        $timetable = $this->getTimetable();

        $coverages = TimetableDailyCoverage::query()
            ->forDate($dateCarbon)
            ->where('timetable_id', $timetable->id)
            ->where('status', 'approved')
            ->with(['absentTeacher:id,name', 'replacementTeacher:id,name', 'period', 'subject:id,name'])
            ->get();

        $report = [];
        $balances = $this->coverageBalances($timetable);

        foreach ($coverages as $c) {
            $rid = $c->replacement_teacher_id;
            $balances[$rid] = $balances[$rid] ?? ['week' => 0, 'month' => 0, 'total' => 0];
            $balances[$rid]['total'] = ($balances[$rid]['total'] ?? 0) + 1;

            $report[$rid]['name'] = $c->replacementTeacher?->name;
            $report[$rid]['extra_periods'] = ($report[$rid]['extra_periods'] ?? 0) + 1;
        }

        $absentReport = $coverages->groupBy('absent_teacher_id')->map(function ($items, $tid) {
            return [
                'teacher_id' => (int) $tid,
                'name' => $items->first()->absentTeacher?->name,
                'absent_periods' => $items->count(),
            ];
        })->values()->all();

        $adjustments = DailyTimetableAdjustment::query()
            ->forDate($dateCarbon)
            ->where('timetable_id', $timetable->id)
            ->whereIn('status', ['approved', 'draft'])
            ->get();

        $swapArchive = $adjustments->map(fn ($a) => [
            'swap_type' => $a->swap_type,
            'teacher_id' => $a->teacher_id,
            'from_period' => $a->original_period_number,
            'to_period' => $a->new_period_number,
        ])->values()->all();

        DB::transaction(function () use ($dateCarbon, $timetable, $coverages, $balances, $report, $absentReport, $swapArchive, $adjustments) {
            TimetableDailyCoverage::query()
                ->forDate($dateCarbon)
                ->where('timetable_id', $timetable->id)
                ->whereIn('status', ['approved', 'draft'])
                ->update(['status' => 'closed']);

            DailyTimetableAdjustment::query()
                ->whereIn('id', $adjustments->pluck('id'))
                ->update(['status' => 'closed']);

            $settings = $timetable->settings ?? [];
            $archive = $settings['coverage_archive'] ?? [];
            $archive[$dateCarbon->toDateString()] = [
                'closed_at' => now()->toIso8601String(),
                'replacements' => array_values($report),
                'absences' => $absentReport,
                'temporary_swaps' => $swapArchive,
            ];
            $settings['coverage_archive'] = $archive;
            $settings['coverage_balances'] = $balances;
            unset($settings['active_daily_coverage']);
            $timetable->update(['settings' => $settings]);
        });

        return [
            'date' => $dateCarbon->toDateString(),
            'replacements' => array_values($report),
            'absences' => $absentReport,
        ];
    }

    /**
     * @return Collection<int, TimetableDailyCoverage>
     */
    public function approvedCoveragesForDate(Carbon $date, ?int $timetableId = null): Collection
    {
        $timetableId = $timetableId ?? $this->getTimetable()->id;

        return TimetableDailyCoverage::query()
            ->forDate($date)
            ->where('timetable_id', $timetableId)
            ->where('status', 'approved')
            ->with(['replacementTeacher:id,name', 'subject:id,name'])
            ->get();
    }

    protected function todayCoveragesMap(Carbon $date, int $timetableId): Collection
    {
        return TimetableDailyCoverage::query()
            ->forDate($date)
            ->where('timetable_id', $timetableId)
            ->whereIn('status', ['draft', 'approved'])
            ->get()
            ->keyBy('timetable_period_id');
    }

    public function coverageBalances(Timetable $timetable): array
    {
        return $timetable->settings['coverage_balances'] ?? [];
    }

    protected function lessonResolutionStatus($coverage, $adjustment): string
    {
        if ($adjustment) {
            return 'approved';
        }
        if ($coverage && $coverage->status === 'approved') {
            return 'approved';
        }
        if ($coverage && $coverage->status === 'draft') {
            return 'draft';
        }

        return 'needs_coverage';
    }

    protected function lessonResolutionType($coverage, $adjustment): string
    {
        if ($adjustment) {
            return in_array($adjustment->swap_type, [
                DailyTimetableAdjustment::SWAP_MOVE,
                DailyTimetableAdjustment::SWAP_EXCHANGE,
            ], true)
                ? 'temporary_swap'
                : 'substitute';
        }
        if ($coverage && $coverage->status === 'approved') {
            return 'substitute';
        }

        return 'pending';
    }

    protected function formatAdjustment(DailyTimetableAdjustment $adj): array
    {
        $secondaryName = $adj->secondary_teacher_id
            ? User::query()->where('id', $adj->secondary_teacher_id)->value('name')
            : null;

        return [
            'id' => $adj->id,
            'swap_type' => $adj->swap_type,
            'swap_type_label' => config('attendance.daily_coverage.swap_types.'.$adj->swap_type, $adj->swap_type),
            'teacher_id' => $adj->teacher_id,
            'teacher_name' => $adj->teacher?->name,
            'secondary_teacher_name' => $secondaryName,
            'from_period' => $adj->original_period_number,
            'to_period' => $adj->new_period_number,
            'impact_preview' => $adj->impact_preview,
            'status' => $adj->status,
        ];
    }

    protected function suggestReplacement(
        TimetablePeriod $period,
        TimetableAssignment $main,
        ?array $absent,
        Collection $teachers,
        array $absentIds,
        Collection $dayPeriods,
        Carbon $date,
        array $coverageBalances,
        Timetable $timetable,
        array $weekCounts = [],
        array $weeklyWorkload = []
    ): array {
        $settings = $timetable->settings ?? [];
        $stageIds = $settings['selected_stages'] ?? [$settings['educational_stage'] ?? 'primary'];
        $maxDaily = config('attendance.daily_coverage.max_daily_substitute_periods', 6);
        $deptPlanIds = $this->departmentPlanTeacherIdsForSubject((int) $main->subject_id, $timetable);
        $todayReplacementCounts = TimetableDailyCoverage::query()
            ->forDate($date)
            ->where('timetable_id', $timetable->id)
            ->whereIn('status', ['draft', 'approved'])
            ->selectRaw('replacement_teacher_id, count(*) as c')
            ->groupBy('replacement_teacher_id')
            ->pluck('c', 'replacement_teacher_id')
            ->map(fn ($c) => (int) $c)
            ->all();
        $historyMap = $this->buildTeacherCoverageHistoryMap(
            (int) $timetable->id,
            $teachers,
            $date,
            $coverageBalances
        );

        $dailyLoads = [];
        $scored = [];
        $busy = [];

        foreach ($teachers as $teacher) {
            if (in_array($teacher->id, $absentIds, true)) {
                continue;
            }

            $evaluation = $this->scoreSubstitute(
                $teacher,
                $period,
                $main,
                $stageIds,
                $coverageBalances,
                $dayPeriods,
                $absentIds,
                $dailyLoads,
                $maxDaily,
                $absent
            );

            if ($evaluation['reject']) {
                if (($evaluation['warnings'][0] ?? '') === 'مشغول في نفس الحصة') {
                    $busy[] = $this->formatSubstituteCandidate(
                        $teacher,
                        $evaluation,
                        $coverageBalances,
                        $weekCounts,
                        false,
                        $deptPlanIds,
                        $weeklyWorkload,
                        $dailyLoads,
                        $todayReplacementCounts,
                        $historyMap
                    );
                }

                continue;
            }

            $scored[] = array_merge($evaluation, [
                'teacher_id' => $teacher->id,
                'name' => $teacher->name,
                'specialization' => $teacher->subjects->pluck('name')->join('، ') ?: ($teacher->department ?? '—'),
            ]);
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        $best = $scored[0] ?? null;
        $alternatives = array_slice($scored, 1, 12);
        $ranked = array_merge($best ? [$best] : [], $alternatives);
        $available = [];
        foreach ($ranked as $index => $a) {
            $available[] = $this->formatSubstituteCandidate(
                $teachers->firstWhere('id', $a['teacher_id']),
                $a,
                $coverageBalances,
                $weekCounts,
                true,
                $deptPlanIds,
                $weeklyWorkload,
                $dailyLoads,
                $todayReplacementCounts,
                $historyMap,
                $index === 0
            );
        }

        if (!$best) {
            return [
                'replacement_teacher_id' => null,
                'replacement_teacher_name' => null,
                'match_percent' => 0,
                'reasons' => [],
                'warnings' => ['لا يوجد معلم متفرغ في هذه الحصة'],
                'alternatives' => [],
                'available_teachers' => [],
                'busy_teachers' => array_slice($busy, 0, 8),
            ];
        }

        $formattedBest = $available[0] ?? $this->formatSubstituteCandidate(
            $teachers->firstWhere('id', $best['teacher_id']),
            $best,
            $coverageBalances,
            $weekCounts,
            true,
            $deptPlanIds,
            $weeklyWorkload,
            $dailyLoads,
            $todayReplacementCounts,
            $historyMap,
            true
        );

        return [
            'replacement_teacher_id' => $best['teacher_id'],
            'replacement_teacher_name' => $best['name'],
            'match_percent' => $formattedBest['match_percent'],
            'reasons' => $best['reasons'],
            'warnings' => $best['warnings'],
            'alternatives' => array_slice($available, 1),
            'available_teachers' => $available,
            'busy_teachers' => array_slice($busy, 0, 8),
            'auto_recommendation' => $formattedBest,
        ];
    }

    protected function formatSubstituteCandidate(
        ?User $teacher,
        array $evaluation,
        array $coverageBalances,
        array $weekCounts,
        bool $isFree,
        array $departmentPlanTeacherIds = [],
        array $weeklyWorkload = [],
        array $dailyLoads = [],
        array $todayReplacementCounts = [],
        array $historyMap = [],
        bool $isAutoRecommendation = false
    ): array {
        $id = $teacher?->id ?? ($evaluation['teacher_id'] ?? 0);
        $balance = (int) ($coverageBalances[$id]['total'] ?? 0);
        $weekExtra = (int) ($weekCounts[$id] ?? 0);
        $maxScore = $this->prioritySettings()->maxPossibleScore();
        $tier = $this->inferPriorityTier($evaluation['matched_priority_keys'] ?? []);
        $workload = $this->workloadFieldsForTeacher($id, $weeklyWorkload);
        $explanation = $evaluation['recommendation_explanation']
            ?? $this->prioritySettings()->recommendationExplanation(
                $evaluation['matched_priority_keys'] ?? [],
                $evaluation['warnings'] ?? []
            );

        return array_merge([
            'teacher_id' => $id,
            'name' => $teacher?->name ?? ($evaluation['name'] ?? '—'),
            'match_percent' => min(100, (int) round(($evaluation['score'] ?? 0) / $maxScore * 100)),
            'priority_reasons' => $evaluation['reasons'] ?? [],
            'priority_tier' => $tier['tier'],
            'priority_tier_label' => $tier['label'],
            'coverage_balance' => $balance,
            'balance_label' => '+'.$balance,
            'coverage_balance_label' => 'رصيد التغطية +'.$balance,
            'extra_this_week' => $weekExtra,
            'is_free' => $isFree,
            'fairness_hint' => $balance <= 2 ? 'أولوية للتوزيع' : ($balance >= 6 ? 'رصيد مرتفع' : 'رصيد متوسط'),
            'from_department_plan' => in_array($id, $departmentPlanTeacherIds, true),
            'recommendation_explanation' => $explanation,
            'is_auto_recommendation' => $isAutoRecommendation,
            'coverage_impact' => $this->coverageImpactForCandidate(
                $id,
                $coverageBalances,
                $weekCounts,
                $dailyLoads,
                $todayReplacementCounts,
                $weeklyWorkload
            ),
            'coverage_history_summary' => $historyMap[$id]['summary'] ?? null,
            'coverage_history' => $historyMap[$id]['entries'] ?? [],
        ], $workload);
    }

    /**
     * Projected fairness impact if this teacher is assigned one more coverage today.
     *
     * @return array<string, mixed>
     */
    protected function coverageImpactForCandidate(
        int $teacherId,
        array $coverageBalances,
        array $weekCounts,
        array $dailyLoads,
        array $todayReplacementCounts,
        array $weeklyWorkload = []
    ): array {
        $balance = (int) ($coverageBalances[$teacherId]['total'] ?? 0);
        $week = (int) ($weekCounts[$teacherId] ?? ($coverageBalances[$teacherId]['week'] ?? 0));
        $today = max(
            (int) ($todayReplacementCounts[$teacherId] ?? 0),
            (int) ($dailyLoads[$teacherId] ?? 0)
        );
        $assigned = (int) ($weeklyWorkload['counts'][$teacherId] ?? 0);
        $max = (int) ($weeklyWorkload['max'] ?? config('attendance.daily_coverage.default_max_weekly_load', 24));
        $afterBalance = $balance + 1;
        $afterWeek = $week + 1;
        $afterToday = $today + 1;
        $afterAssigned = min($assigned + 1, $max);
        $risk = $afterBalance >= 6 ? 'high' : ($afterBalance >= 3 ? 'mid' : 'low');
        $riskLabels = ['low' => 'منخفض', 'mid' => 'متوسط', 'high' => 'مرتفع'];

        return [
            'risk_level' => $risk,
            'risk_label' => $riskLabels[$risk],
            'balance_before_label' => '+'.$balance,
            'balance_after_label' => '+'.$afterBalance,
            'balance_transition' => '+'.$balance.' → +'.$afterBalance,
            'workload_before_label' => "{$assigned} / {$max} حصص",
            'workload_after_label' => "{$afterAssigned} / {$max} حصص",
            'today_after' => $afterToday,
            'week_after' => $afterWeek,
            'summary_lines' => [
                'رصيد التغطية يصبح +'.$afterBalance,
                'تغطيات اليوم: '.$afterToday,
                'هذا الأسبوع: '.$afterWeek.' تغطية',
            ],
        ];
    }

    /**
     * @return array<int, array{summary: array<string, mixed>, entries: array<int, array<string, mixed>>}>
     */
    protected function buildTeacherCoverageHistoryMap(
        int $timetableId,
        Collection $teachers,
        Carbon $referenceDate,
        array $coverageBalances = []
    ): array {
        $records = TimetableDailyCoverage::query()
            ->where('timetable_id', $timetableId)
            ->whereIn('status', ['approved', 'closed'])
            ->with('subject:id,name')
            ->orderByDesc('coverage_date')
            ->limit(300)
            ->get(['replacement_teacher_id', 'coverage_date', 'status', 'match_score', 'subject_id']);

        $weekStart = $referenceDate->copy()->startOfWeek();
        $monthStart = $referenceDate->copy()->startOfMonth();
        $grouped = $records->groupBy('replacement_teacher_id');
        $map = [];

        foreach ($teachers as $teacher) {
            $teacherRecords = $grouped[$teacher->id] ?? collect();
            $weekCount = (int) ($coverageBalances[$teacher->id]['week'] ?? 0);
            if ($weekCount === 0) {
                $weekCount = $teacherRecords
                    ->filter(fn ($row) => $row->coverage_date->gte($weekStart))
                    ->count();
            }
            $monthCount = (int) ($coverageBalances[$teacher->id]['month'] ?? 0);
            if ($monthCount === 0) {
                $monthCount = $teacherRecords
                    ->filter(fn ($row) => $row->coverage_date->gte($monthStart))
                    ->count();
            }

            $entries = $teacherRecords
                ->take(5)
                ->map(function ($row) {
                    $dayLabel = $this->arabicDayNameForDate($row->coverage_date);
                    $subject = $row->subject?->name ?? '—';
                    $score = (int) $row->match_score;

                    return [
                        'date' => $row->coverage_date->format('Y-m-d'),
                        'day_label' => $dayLabel,
                        'subject_name' => $subject,
                        'status' => $row->status,
                        'match_score' => $score,
                        'line_label' => "{$dayLabel} – {$subject} – {$score}%",
                    ];
                })
                ->values()
                ->all();

            $last = $entries[0] ?? null;

            $map[$teacher->id] = [
                'summary' => [
                    'week_count' => $weekCount,
                    'month_count' => $monthCount,
                    'last_coverage_day_label' => $last['day_label'] ?? null,
                    'last_coverage_date' => $last['date'] ?? null,
                ],
                'entries' => $entries,
            ];
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildDepartmentInsights(
        Collection $affected,
        Timetable $timetable,
        array $departmentPlanTeacherIds
    ): array {
        $distribution = $timetable->settings['department_plan']['teacher_load_distribution'] ?? [];
        $assignedFromPlan = 0;
        $candidatesFromPlan = 0;

        foreach ($affected as $lesson) {
            $rep = $lesson['suggestion']['replacement_teacher_id'] ?? null;
            if ($rep && in_array((int) $rep, $departmentPlanTeacherIds, true)) {
                $assignedFromPlan++;
            }
            foreach ($lesson['available_teachers'] ?? [] as $candidate) {
                if (!empty($candidate['from_department_plan'])) {
                    $candidatesFromPlan++;
                }
            }
        }

        $uncovered = $affected->filter(function ($lesson) {
            if (!empty($lesson['adjustment'])) {
                return false;
            }

            return empty($lesson['suggestion']['replacement_teacher_id']);
        })->count();

        $insights = [];
        if (!empty($distribution)) {
            $insights[] = [
                'icon' => 'bi-diagram-3',
                'text' => 'خطة القسم مفعّلة — '.count($departmentPlanTeacherIds).' معلم/معلمين في التوزيع',
            ];
        }
        if ($assignedFromPlan > 0) {
            $insights[] = [
                'icon' => 'bi-check2-circle',
                'text' => $assignedFromPlan.' حصة مُغطّاة بمرشحين من خطة القسم',
            ];
        }
        if ($candidatesFromPlan > 0 && $uncovered > 0) {
            $insights[] = [
                'icon' => 'bi-lightbulb',
                'text' => 'يوجد '.$candidatesFromPlan.' مرشحاً من خطة القسم للحصص غير المغطاة',
            ];
        }
        if ($uncovered > 0) {
            $insights[] = [
                'icon' => 'bi-exclamation-triangle',
                'text' => $uncovered.' حصة ما زالت بانتظار التغطية',
            ];
        }

        return [
            'has_department_plan' => !empty($distribution),
            'department_plan_teacher_count' => count($departmentPlanTeacherIds),
            'subjects_affected' => $affected->pluck('subject_id')->unique()->filter()->count(),
            'assignments_from_plan' => $assignedFromPlan,
            'insights' => $insights,
        ];
    }

    protected function replacementCountsSinceWeekStart(Carbon $date): array
    {
        $weekStart = $date->copy()->startOfWeek();

        return TimetableDailyCoverage::query()
            ->where('status', 'approved')
            ->whereDate('coverage_date', '>=', $weekStart)
            ->whereDate('coverage_date', '<=', $date)
            ->selectRaw('replacement_teacher_id, count(*) as c')
            ->groupBy('replacement_teacher_id')
            ->pluck('c', 'replacement_teacher_id')
            ->all();
    }

    /**
     * كل المعلمين مع رصيد التغطية — الأقل رصيداً أولاً (أنسب للتكليف الجديد).
     */
    protected function buildCoverageRoster(Collection $teachers, array $balances, array $weekCounts, array $weeklyWorkload = []): array
    {
        return $teachers
            ->map(function ($t) use ($balances, $weekCounts, $weeklyWorkload) {
                $total = (int) ($balances[$t->id]['total'] ?? 0);
                $week = (int) ($balances[$t->id]['week'] ?? $weekCounts[$t->id] ?? 0);

                return array_merge([
                    'teacher_id' => $t->id,
                    'name' => $t->name,
                    'coverage_balance' => $total,
                    'balance_label' => '+'.$total,
                    'coverage_balance_label' => 'رصيد التغطية +'.$total,
                    'extra_this_week' => $week,
                    'fairness_hint' => $total <= 2 ? 'أولوية' : ($total >= 6 ? 'مرتفع' : 'متوسط'),
                ], $this->workloadFieldsForTeacher((int) $t->id, $weeklyWorkload));
            })
            ->sortBy('coverage_balance')
            ->values()
            ->all();
    }

    protected function scoreSubstitute(
        User $teacher,
        TimetablePeriod $period,
        TimetableAssignment $main,
        array $stageIds,
        array $coverageBalances,
        Collection $dayPeriods,
        array $absentIds,
        array &$dailyLoads,
        int $maxDaily,
        ?array $absentTeacher = null
    ): array {
        if ($this->isTeacherBusyAtPeriod($dayPeriods, (int) $period->period_number, $teacher->id, $absentIds)) {
            return ['score' => -1, 'reject' => true, 'reasons' => [], 'warnings' => ['مشغول في نفس الحصة']];
        }

        $dailyLoads[$teacher->id] = ($dailyLoads[$teacher->id] ?? 0);
        if ($dailyLoads[$teacher->id] >= $maxDaily) {
            return ['score' => -1, 'reject' => true, 'reasons' => [], 'warnings' => ['تجاوز الحد اليومي']];
        }

        return $this->prioritySettings()->scoreSubstitute(
            $teacher,
            $period,
            $main,
            $stageIds,
            $coverageBalances,
            $absentTeacher
        );
    }

    protected function isTeacherBusyAtPeriod(Collection $periods, int $periodNumber, int $teacherId, array $absentIds): bool
    {
        foreach ($periods as $period) {
            if ((int) $period->period_number !== $periodNumber) {
                continue;
            }
            foreach ($period->assignments as $assignment) {
                if (
                    $assignment->type === 'main'
                    && (int) $assignment->teacher_id === $teacherId
                    && !in_array($assignment->teacher_id, $absentIds, true)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function formatExistingSuggestion(
        TimetableDailyCoverage $existing,
        Collection $teachers,
        array $balances,
        array $weekCounts = []
    ): array {
        $replacement = $teachers->firstWhere('id', $existing->replacement_teacher_id);
        $balance = (int) ($balances[$existing->replacement_teacher_id]['total'] ?? 0);

        return [
            'replacement_teacher_id' => $existing->replacement_teacher_id,
            'replacement_teacher_name' => $replacement?->name,
            'match_percent' => $existing->match_score,
            'reasons' => $existing->match_reasons ?? [],
            'warnings' => [],
            'alternatives' => [],
            'available_teachers' => [[
                'teacher_id' => $existing->replacement_teacher_id,
                'name' => $replacement?->name,
                'match_percent' => $existing->match_score,
                'coverage_balance' => $balance,
                'balance_label' => '+'.$balance,
                'extra_this_week' => (int) ($weekCounts[$existing->replacement_teacher_id] ?? 0),
                'is_free' => true,
                'fairness_hint' => 'معتمد',
            ]],
            'busy_teachers' => [],
            'approved' => $existing->status === 'approved',
        ];
    }

    protected function buildCoveragePlan(Collection $affected, Collection $todayCoverages, Collection $adjustments): array
    {
        return $affected->map(function ($lesson) use ($todayCoverages, $adjustments) {
            $cov = $todayCoverages->get($lesson['period_id']);
            $s = $lesson['suggestion'];
            $adj = $adjustments->firstWhere('trigger_period_id', $lesson['period_id']);

            $replacement = $s['replacement_teacher_name'] ?? '—';
            $badge = null;
            if ($adj) {
                $replacement = $adj->teacher?->name.' (نقل مؤقت)';
                $badge = 'مؤقت';
            }

            return [
                'period_number' => $lesson['period_number'],
                'time' => "{$lesson['time_from']} - {$lesson['time_to']}",
                'subject' => $lesson['subject_name'],
                'original_teacher' => $lesson['absent_teacher_name'],
                'replacement_teacher' => $replacement,
                'status' => $cov?->status ?? ($adj ? 'swap' : 'pending'),
                'temporary_badge' => $badge,
                'temporary_tooltip' => 'تعديل يومي لا يؤثر على الجدول الأساسي',
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array{teacher_id: int, slots: array<int, array>}>
     */
    protected function buildTeacherSchedules(
        Collection $absentTeachers,
        Collection $dayPeriods,
        Collection $affected,
        int $dayId
    ): array {
        $periodNumbers = $dayPeriods
            ->pluck('period_number')
            ->map(fn ($n) => (int) $n)
            ->filter(fn ($n) => $n > 0)
            ->unique()
            ->sort()
            ->values();

        $schedules = [];

        foreach ($absentTeachers as $absent) {
            $teacherId = (int) $absent['teacher_id'];
            $assignmentsByPeriod = TimetableAssignment::query()
                ->where('type', 'main')
                ->where('teacher_id', $teacherId)
                ->whereHas('period', fn ($q) => $q->where('timetable_day_id', $dayId)->where('period_number', '>', 0))
                ->with(['period.category', 'subject'])
                ->get()
                ->groupBy(fn ($a) => (int) $a->period->period_number);

            $slots = [];
            foreach ($periodNumbers as $periodNumber) {
                $assigns = $assignmentsByPeriod->get($periodNumber, collect());
                if ($assigns->isEmpty()) {
                    $ref = $dayPeriods->firstWhere('period_number', $periodNumber);
                    $slots[] = [
                        'period_id' => null,
                        'period_number' => $periodNumber,
                        'time_from' => $ref ? substr((string) $ref->time_from, 0, 5) : null,
                        'time_to' => $ref ? substr((string) $ref->time_to, 0, 5) : null,
                        'subject_name' => null,
                        'class_name' => null,
                        'is_free' => true,
                        'label' => 'فراغ',
                        'coverage_status' => 'free',
                        'coverage_status_label' => '—',
                    ];

                    continue;
                }

                foreach ($assigns as $assign) {
                    $period = $assign->period;
                    $lesson = $affected->firstWhere('period_id', $period->id);
                    $status = $this->slotCoverageStatus($lesson);
                    $slots[] = [
                        'period_id' => $period->id,
                        'period_number' => $periodNumber,
                        'time_from' => substr((string) $period->time_from, 0, 5),
                        'time_to' => substr((string) $period->time_to, 0, 5),
                        'subject_name' => $assign->subject?->name ?? '—',
                        'class_name' => $period->category?->name ?? '—',
                        'is_free' => false,
                        'label' => null,
                        'coverage_status' => $status['key'],
                        'coverage_status_label' => $status['label'],
                        'lesson' => $lesson,
                    ];
                }
            }

            $schedules[$teacherId] = [
                'teacher_id' => $teacherId,
                'slots' => $slots,
                'teaching_periods' => $assignmentsByPeriod->flatten()->count(),
            ];
        }

        return $schedules;
    }

    /**
     * @return array{key: string, label: string}
     */
    protected function slotCoverageStatus(?array $lesson): array
    {
        if (!$lesson) {
            return ['key' => 'free', 'label' => '—'];
        }
        if (!empty($lesson['adjustment']) || ($lesson['resolution'] ?? '') === 'temporary_swap') {
            return ['key' => 'covered', 'label' => 'تمت التغطية'];
        }
        if (($lesson['status'] ?? '') === 'approved') {
            return ['key' => 'covered', 'label' => 'تمت التغطية'];
        }
        if (!empty($lesson['suggestion']['replacement_teacher_id'])) {
            return ['key' => 'pending', 'label' => 'قيد التغطية'];
        }

        return ['key' => 'uncovered', 'label' => 'غير مغطاة'];
    }

    /**
     * @return array{tier: int, label: string}
     */
    protected function inferPriorityTier(array $matchedKeys): array
    {
        $hasSubject = in_array('same_subject', $matchedKeys, true);
        $hasStage = in_array('same_stage', $matchedKeys, true);
        $hasDept = in_array('same_department', $matchedKeys, true);

        if ($hasSubject && $hasStage) {
            return ['tier' => 1, 'label' => 'نفس المادة + المرحلة'];
        }
        if ($hasSubject) {
            return ['tier' => 2, 'label' => 'نفس المادة'];
        }
        if ($hasDept) {
            return ['tier' => 3, 'label' => 'نفس القسم'];
        }

        return ['tier' => 4, 'label' => 'معلم متاح'];
    }

    /**
     * @return array<int>
     */
    protected function departmentPlanTeacherIds(Timetable $timetable): array
    {
        $distribution = $timetable->settings['department_plan']['teacher_load_distribution'] ?? [];
        $ids = [];
        foreach ($distribution as $rows) {
            foreach ($rows as $row) {
                if (!empty($row['teacher_id'])) {
                    $ids[] = (int) $row['teacher_id'];
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<int>
     */
    protected function departmentPlanTeacherIdsForSubject(int $subjectId, Timetable $timetable): array
    {
        $rows = $timetable->settings['department_plan']['teacher_load_distribution'][(string) $subjectId] ?? [];

        return collect($rows)->pluck('teacher_id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildCoverageReport(Collection $affected, Collection $teachers, array $coverageBalances): array
    {
        $total = $affected->count();
        $covered = $affected->filter(function ($l) {
            if (!empty($l['adjustment']) || ($l['resolution'] ?? '') === 'temporary_swap') {
                return true;
            }

            return ($l['status'] ?? '') === 'approved'
                || !empty($l['suggestion']['replacement_teacher_id']);
        })->count();
        $uncovered = max(0, $total - $covered);

        $loads = [];
        foreach ($affected as $lesson) {
            $tid = $lesson['suggestion']['replacement_teacher_id'] ?? null;
            if (!$tid && empty($lesson['adjustment'])) {
                continue;
            }
            if ($tid) {
                $loads[$tid] = ($loads[$tid] ?? 0) + 1;
            }
        }

        $distribution = [];
        foreach ($loads as $teacherId => $count) {
            $teacher = $teachers->firstWhere('id', $teacherId);
            $balance = (int) ($coverageBalances[$teacherId]['total'] ?? 0);
            $distribution[] = [
                'teacher_id' => $teacherId,
                'teacher_name' => $teacher?->name ?? '—',
                'coverage_count' => $count,
                'balance_label' => '+'.$balance,
                'coverage_balance' => $balance,
            ];
        }

        usort($distribution, fn ($a, $b) => $b['coverage_count'] <=> $a['coverage_count']);

        $mostLoaded = $distribution[0] ?? null;
        $leastLoaded = $distribution ? $distribution[count($distribution) - 1] : null;

        $completionPercent = $total > 0 ? (int) round(($covered / $total) * 100) : 0;

        return [
            'affected_total' => $total,
            'covered_count' => $covered,
            'uncovered_count' => $uncovered,
            'completion_percent' => $completionPercent,
            'distribution' => $distribution,
            'most_loaded' => $mostLoaded,
            'least_loaded' => $leastLoaded,
        ];
    }

    /**
     * @return array{max: int, counts: array<int, int>}
     */
    protected function teacherWeeklyWorkload(Timetable $timetable): array
    {
        $max = (int) (
            $timetable->settings['max_weekly_teacher_load']
            ?? $timetable->settings['teacher_distribution_max_weekly_load']
            ?? config('attendance.daily_coverage.default_max_weekly_load', 24)
        );

        $counts = TimetableAssignment::query()
            ->where('type', 'main')
            ->whereHas('period', fn ($q) => $q->where('timetable_id', $timetable->id))
            ->selectRaw('teacher_id, COUNT(*) as period_count')
            ->groupBy('teacher_id')
            ->pluck('period_count', 'teacher_id')
            ->map(fn ($c) => (int) $c)
            ->all();

        return ['max' => $max, 'counts' => $counts];
    }

    /**
     * @param  array{max: int, counts: array<int, int>}  $workload
     * @return array{weekly_assigned_periods: int, max_weekly_load: int, workload_label: string}
     */
    protected function workloadFieldsForTeacher(int $teacherId, array $workload): array
    {
        $assigned = (int) ($workload['counts'][$teacherId] ?? 0);
        $max = (int) ($workload['max'] ?? config('attendance.daily_coverage.default_max_weekly_load', 24));

        return [
            'weekly_assigned_periods' => $assigned,
            'max_weekly_load' => $max,
            'workload_label' => "{$assigned} / {$max} حصص",
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function draftMetaForDate(Timetable $timetable, string $date): ?array
    {
        $meta = ($timetable->settings['coverage_draft_meta'] ?? [])[$date] ?? null;
        if (!$meta) {
            return null;
        }

        $savedAt = $meta['saved_at'] ?? null;
        $label = $meta['saved_at_label'] ?? null;
        if ($savedAt && !$label) {
            try {
                $label = Carbon::parse($savedAt)->format('Y-m-d H:i');
            } catch (\Throwable) {
                $label = $savedAt;
            }
        }

        return array_merge($meta, [
            'saved_at_label' => $label,
            'banner_text' => $label ? "مسودة محفوظة بتاريخ {$label}" : 'مسودة محفوظة',
        ]);
    }

}
