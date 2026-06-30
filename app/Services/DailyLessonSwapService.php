<?php

namespace App\Services;

use App\Models\DailyTimetableAdjustment;
use App\Models\TeacherAttendance;
use App\Models\Timetable;
use App\Models\TimetableAssignment;
use App\Models\TimetableDay;
use App\Models\TimetablePeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailyLessonSwapService
{
    public function __construct(
        protected DailyAbsenceCoverageService $coverageService,
    ) {}

    /**
     * @return Collection<int, DailyTimetableAdjustment>
     */
    public function activeAdjustmentsForDate(Carbon $date, ?int $timetableId = null): Collection
    {
        $timetableId = $timetableId ?? $this->coverageService->getTimetable()->id;

        return DailyTimetableAdjustment::query()
            ->forDate($date)
            ->where('timetable_id', $timetableId)
            ->active()
            ->with([
                'teacher:id,name',
                'period.assignments' => fn ($q) => $q->where('type', 'main'),
                'period.category:id,name',
                'period.assignments.subject:id,name',
                'targetPeriod.category:id,name',
            ])
            ->get();
    }

    /**
     * Teachers eligible for temporary swap (move/swap) for a given absent-affected period.
     */
    public function swapCandidates(string $date, int $triggerPeriodId): array
    {
        $dateCarbon = Carbon::parse($date);
        $timetable = $this->coverageService->getTimetable();
        $dayName = $this->coverageService->arabicDayNameForDate($dateCarbon);
        $day = TimetableDay::query()
            ->where('timetable_id', $timetable->id)
            ->where('day_name', $dayName)
            ->first();

        if (!$day) {
            return ['candidates' => [], 'trigger_period' => null];
        }

        $triggerPeriod = TimetablePeriod::with([
            'category:id,name',
            'assignments' => fn ($q) => $q->where('type', 'main'),
            'assignments.teacher:id,name',
            'assignments.subject:id,name',
        ])->findOrFail($triggerPeriodId);

        $absentIds = $this->coverageService->absentTeachersForDate($dateCarbon)->pluck('teacher_id')->all();
        $dayPeriods = $this->loadDayPeriods($timetable->id, $day->id);
        $adjustments = $this->activeAdjustmentsForDate($dateCarbon, $timetable->id);
        $coverageBalances = $this->coverageService->coverageBalances($timetable);

        $teachers = User::query()
            ->where('user_type', 'teacher')
            ->with(['subjects:id,name'])
            ->get(['id', 'name', 'email', 'department', 'job_title']);

        $triggerMain = $triggerPeriod->assignments->first();
        $triggerSubjectId = $triggerMain?->subject_id;

        $candidates = [];
        foreach ($teachers as $teacher) {
            if (in_array($teacher->id, $absentIds, true)) {
                continue;
            }

            $schedule = $this->teacherDaySchedule(
                $teacher,
                $dayPeriods,
                $adjustments,
                $absentIds,
                $dateCarbon
            );

            $movable = collect($schedule)->filter(fn ($s) => $s['has_lesson'] && $s['period_id'])->values();
            if ($movable->isEmpty()) {
                continue;
            }

            $freeSlots = collect($schedule)->filter(fn ($s) => $s['is_free'])->pluck('period_number')->all();
            $targetPeriodNumber = (int) $triggerPeriod->period_number;
            if (!in_array($targetPeriodNumber, $freeSlots, true)) {
                continue;
            }

            $score = $this->scoreSwapCandidate($teacher, $triggerPeriod, $triggerSubjectId, $coverageBalances, $timetable);

                $suggestedMove = null;
            $bestLesson = $movable->sortByDesc('period_number')->first();
            if ($bestLesson && $bestLesson['period_number'] > $targetPeriodNumber) {
                $targetPeriod = $this->resolveTargetPeriodForMove(
                    $teacher->id,
                    $dayPeriods,
                    $targetPeriodNumber,
                    (int) ($bestLesson['category_id'] ?? $triggerPeriod->category_id)
                );
                $suggestedMove = [
                    'from_period_number' => $bestLesson['period_number'],
                    'to_period_number' => $targetPeriodNumber,
                    'from_period_id' => $bestLesson['period_id'],
                    'to_period_id' => $targetPeriod?->id,
                ];
            }

            $candidates[] = [
                'teacher_id' => $teacher->id,
                'name' => $teacher->name,
                'specialization' => $teacher->subjects->pluck('name')->join('، ') ?: ($teacher->department ?? '—'),
                'match_score' => $score,
                'schedule' => $schedule,
                'suggested_move' => $suggestedMove,
            ];
        }

        usort($candidates, fn ($a, $b) => $b['match_score'] <=> $a['match_score']);

        $exchangeTeachers = $this->buildExchangeTeacherPool($teachers, $dayPeriods, $adjustments, $absentIds, $dateCarbon);

        return [
            'date' => $dateCarbon->toDateString(),
            'day_name' => $dayName,
            'trigger_period' => [
                'period_id' => $triggerPeriod->id,
                'period_number' => $triggerPeriod->period_number,
                'class_name' => $triggerPeriod->category?->name,
                'subject_name' => $triggerMain?->subject?->name,
                'absent_teacher_name' => $triggerMain?->teacher?->name,
            ],
            'candidates' => array_slice($candidates, 0, 15),
            'exchange_teachers' => $exchangeTeachers,
            'swap_types' => config('attendance.daily_coverage.swap_types', []),
        ];
    }

    /**
     * @return array<int, array{teacher_id: int, name: string, lessons: array}>
     */
    protected function buildExchangeTeacherPool(
        Collection $teachers,
        Collection $dayPeriods,
        Collection $adjustments,
        array $absentIds,
        Carbon $date
    ): array {
        $pool = [];

        foreach ($teachers as $teacher) {
            if (in_array($teacher->id, $absentIds, true)) {
                continue;
            }

            $schedule = $this->teacherDaySchedule($teacher, $dayPeriods, $adjustments, $absentIds, $date);
            $lessons = collect($schedule)
                ->filter(fn ($s) => $s['has_lesson'] && $s['period_id'])
                ->map(fn ($s) => [
                    'period_id' => $s['period_id'],
                    'period_number' => $s['period_number'],
                    'label' => $s['label'],
                    'subject_name' => $s['subject_name'],
                ])
                ->values()
                ->all();

            if ($lessons === []) {
                continue;
            }

            $pool[] = [
                'teacher_id' => $teacher->id,
                'name' => $teacher->name,
                'lessons' => $lessons,
            ];
        }

        usort($pool, fn ($a, $b) => count($b['lessons']) <=> count($a['lessons']));

        return array_slice($pool, 0, 25);
    }

    public function previewSwap(array $payload): array
    {
        $this->validateSwapPayload($payload, previewOnly: true);

        return $this->buildImpactPreview($payload);
    }

    public function applySwap(array $payload, ?int $userId = null): DailyTimetableAdjustment
    {
        $preview = $this->previewSwap($payload);
        if (!empty($preview['errors'])) {
            throw ValidationException::withMessages(['swap' => $preview['errors']]);
        }

        $date = Carbon::parse($payload['date']);
        $timetable = $this->coverageService->getTimetable();

        return DB::transaction(function () use ($payload, $preview, $date, $timetable, $userId) {
            $sourcePeriod = TimetablePeriod::with('assignments')->findOrFail($payload['source_period_id']);
            $main = $sourcePeriod->assignments->where('type', 'main')->first();

            return DailyTimetableAdjustment::create([
                'adjustment_date' => $date->toDateString(),
                'timetable_id' => $timetable->id,
                'swap_type' => $payload['swap_type'],
                'teacher_id' => (int) $payload['teacher_id'],
                'timetable_period_id' => (int) $payload['source_period_id'],
                'target_timetable_period_id' => $payload['target_period_id'] ?? null,
                'secondary_teacher_id' => $payload['secondary_teacher_id'] ?? null,
                'secondary_timetable_period_id' => $payload['secondary_period_id'] ?? null,
                'replacement_teacher_id' => $payload['replacement_teacher_id'] ?? null,
                'trigger_period_id' => $payload['trigger_period_id'] ?? $payload['target_period_id'] ?? null,
                'subject_id' => $main?->subject_id,
                'category_id' => $sourcePeriod->category_id,
                'original_period_number' => (int) $sourcePeriod->period_number,
                'new_period_number' => $this->resolveNewPeriodNumber($payload, $sourcePeriod),
                'reason' => $payload['reason'] ?? 'تبديل حصة مؤقت — تغطية غياب',
                'impact_preview' => $preview,
                'status' => 'approved',
                'created_by' => $userId,
            ]);
        });
    }

    public function cancelAdjustment(int $adjustmentId): void
    {
        DailyTimetableAdjustment::query()
            ->where('id', $adjustmentId)
            ->whereIn('status', ['draft', 'approved'])
            ->update(['status' => 'cancelled']);
    }

    public function cancelLessonResolution(string $date, int $periodId): void
    {
        $dateCarbon = Carbon::parse($date);
        $timetableId = $this->coverageService->getTimetable()->id;

        DailyTimetableAdjustment::query()
            ->forDate($dateCarbon)
            ->where('timetable_id', $timetableId)
            ->where(fn ($q) => $q
                ->where('timetable_period_id', $periodId)
                ->orWhere('target_timetable_period_id', $periodId)
                ->orWhere('trigger_period_id', $periodId))
            ->active()
            ->update(['status' => 'cancelled']);
    }

    /**
     * Resolve display for a student's period (today only) — never mutates permanent timetable.
     */
    public function resolvePeriodForStudent(
        TimetablePeriod $period,
        Carbon $date,
        Collection $coverages,
        Collection $adjustments,
        bool $isTodaySlot
    ): array {
        $main = $period->assignments->first();
        $base = [
            'subject' => $main?->subject?->name,
            'teacher' => $main?->teacher?->name,
            'teacher_id' => $main?->teacher_id,
            'is_temporary' => false,
            'temporary_label' => null,
            'temporary_tooltip' => null,
            'schedule_note' => null,
        ];

        if (!$isTodaySlot) {
            return $base;
        }

        $coverage = $coverages->get($period->id);
        if ($coverage) {
            return array_merge($base, [
                'subject' => $coverage->subject?->name ?? $base['subject'],
                'teacher' => $coverage->replacementTeacher?->name,
                'teacher_id' => $coverage->replacement_teacher_id,
                'substitute_teacher' => $coverage->replacementTeacher?->name,
                'display_teacher' => $coverage->replacementTeacher?->name,
                'is_coverage_today' => true,
                'is_temporary' => true,
                'temporary_label' => 'تغطية اليوم',
                'temporary_tooltip' => 'تعديل يومي لا يؤثر على الجدول الأساسي',
                'schedule_note' => 'تم تعديل الحصة اليوم',
            ]);
        }

        foreach ($adjustments as $adj) {
            if ($adj->status !== 'approved' && $adj->status !== 'draft') {
                continue;
            }

            if ($adj->swap_type === DailyTimetableAdjustment::SWAP_MOVE) {
                if ((int) $adj->timetable_period_id === (int) $period->id) {
                    return array_merge($base, [
                        'subject' => null,
                        'teacher' => null,
                        'teacher_id' => null,
                        'is_temporary' => true,
                        'temporary_label' => 'مؤقت',
                        'temporary_tooltip' => 'تعديل يومي لا يؤثر على الجدول الأساسي',
                        'schedule_note' => 'تم نقل الحصة مؤقتاً',
                    ]);
                }
                if ((int) $adj->target_timetable_period_id === (int) $period->id) {
                    $preview = $adj->impact_preview ?? [];
                    $after = collect($preview['after'] ?? [])->firstWhere('period_number', $period->period_number);

                    return array_merge($base, [
                        'subject' => $after['subject_name'] ?? $adj->period?->assignments->first()?->subject?->name,
                        'teacher' => $adj->teacher?->name,
                        'teacher_id' => $adj->teacher_id,
                        'is_temporary' => true,
                        'temporary_label' => 'مؤقت',
                        'temporary_tooltip' => 'تعديل يومي لا يؤثر على الجدول الأساسي',
                        'schedule_note' => 'تم تعديل الحصة اليوم',
                    ]);
                }
            }

            if ($adj->swap_type === DailyTimetableAdjustment::SWAP_EXCHANGE) {
                if ((int) $adj->timetable_period_id === (int) $period->id) {
                    $preview = $adj->impact_preview ?? [];
                    $after = collect($preview['after'] ?? [])->firstWhere('period_id', $period->id);

                    return array_merge($base, [
                        'subject' => $after['subject_name'] ?? $base['subject'],
                        'teacher' => $after['teacher_name'] ?? $base['teacher'],
                        'teacher_id' => $after['teacher_id'] ?? $base['teacher_id'],
                        'is_temporary' => true,
                        'temporary_label' => 'مؤقت',
                        'temporary_tooltip' => 'تعديل يومي لا يؤثر على الجدول الأساسي',
                        'schedule_note' => 'تم تبديل الحصة اليوم',
                    ]);
                }
                if ((int) $adj->secondary_timetable_period_id === (int) $period->id) {
                    $preview = $adj->impact_preview ?? [];
                    $after = collect($preview['after'] ?? [])->firstWhere('period_id', $period->id);

                    return array_merge($base, [
                        'subject' => $after['subject_name'] ?? $base['subject'],
                        'teacher' => $after['teacher_name'] ?? $base['teacher'],
                        'is_temporary' => true,
                        'temporary_label' => 'مؤقت',
                        'schedule_note' => 'تم تبديل الحصة اليوم',
                    ]);
                }
            }
        }

        return $base;
    }

    protected function buildImpactPreview(array $payload): array
    {
        $errors = [];
        $swapType = $payload['swap_type'];
        $teacherId = (int) $payload['teacher_id'];
        $date = Carbon::parse($payload['date']);

        $timetable = $this->coverageService->getTimetable();
        $dayName = $this->coverageService->arabicDayNameForDate($date);
        $day = TimetableDay::query()
            ->where('timetable_id', $timetable->id)
            ->where('day_name', $dayName)
            ->first();

        if (!$day) {
            return ['before' => [], 'after' => [], 'errors' => ['لا يوجد يوم دراسي']];
        }

        $dayPeriods = $this->loadDayPeriods($timetable->id, $day->id);
        $absentIds = $this->coverageService->absentTeachersForDate($date)->pluck('teacher_id')->all();
        $adjustments = $this->activeAdjustmentsForDate($date, $timetable->id);
        $teacher = User::find($teacherId);

        if (!$teacher || in_array($teacherId, $absentIds, true)) {
            $errors[] = 'المعلم غير متاح أو غائب';
        }

        $before = [];
        $after = [];

        if ($swapType === 'move_lesson') {
            $source = TimetablePeriod::with(['assignments.subject', 'category'])->find($payload['source_period_id']);
            $target = TimetablePeriod::with('category')->find($payload['target_period_id'] ?? null);
            if (!$source || !$target) {
                $errors[] = 'حصة المصدر أو الهدف غير موجودة';
            } else {
                $main = $source->assignments->where('type', 'main')->first();
                if (!$main || (int) $main->teacher_id !== $teacherId) {
                    $errors[] = 'المعلم لا يملك الحصة المصدر';
                }
                if ($this->isTeacherBusyAtPeriodEffective(
                    $dayPeriods,
                    (int) $target->period_number,
                    $teacherId,
                    $absentIds,
                    $adjustments,
                    excludePeriodId: (int) $source->id
                )) {
                    $errors[] = 'المعلم مشغول في الحصة المستهدفة';
                }

                $periodNumbers = $dayPeriods->pluck('period_number')->unique()->sort()->values();
                foreach ($periodNumbers as $num) {
                    $slot = $this->slotLabelForTeacher($teacherId, (int) $num, $dayPeriods, $adjustments, $absentIds);
                    $before[] = ['period_number' => (int) $num, 'label' => $slot['label'], 'period_id' => $slot['period_id']];
                }
                foreach ($before as $row) {
                    $after[] = $this->applyMoveToRow($row, $source, $target, $main, $teacher);
                }
            }
        } elseif ($swapType === 'swap_lessons') {
            $periodA = TimetablePeriod::with(['assignments.subject', 'assignments.teacher', 'category'])->find($payload['source_period_id']);
            $periodB = TimetablePeriod::with(['assignments.subject', 'assignments.teacher', 'category'])->find($payload['secondary_period_id'] ?? null);
            $teacherBId = (int) ($payload['secondary_teacher_id'] ?? 0);
            if (!$periodA || !$periodB) {
                $errors[] = 'حصص التبديل غير مكتملة';
            } elseif ((int) $periodA->id === (int) $periodB->id) {
                $errors[] = 'لا يمكن تبديل الحصة مع نفسها';
            } else {
                $mainA = $periodA->assignments->where('type', 'main')->first();
                $mainB = $periodB->assignments->where('type', 'main')->first();
                if (!$mainA || !$mainB) {
                    $errors[] = 'إحدى الحصص بدون تعيين';
                } elseif ((int) $mainA->teacher_id !== $teacherId) {
                    $errors[] = 'المعلم الأول لا يملك الحصة المحددة';
                } elseif ((int) $mainB->teacher_id !== $teacherBId) {
                    $errors[] = 'المعلم الثاني لا يملك الحصة المحددة';
                } elseif (in_array($teacherBId, $absentIds, true) || in_array($teacherId, $absentIds, true)) {
                    $errors[] = 'لا يمكن تبديل حصص معلم غائب';
                } elseif ($teacherId === $teacherBId) {
                    $errors[] = 'اختر معلمين مختلفين';
                } else {
                    if ($this->isTeacherBusyAtPeriodEffective(
                        $dayPeriods,
                        (int) $periodB->period_number,
                        $teacherId,
                        $absentIds,
                        $adjustments,
                        excludePeriodId: (int) $periodA->id
                    )) {
                        $errors[] = 'المعلم الأول مشغول في توقيت الحصة الثانية';
                    }
                    if ($this->isTeacherBusyAtPeriodEffective(
                        $dayPeriods,
                        (int) $periodA->period_number,
                        $teacherBId,
                        $absentIds,
                        $adjustments,
                        excludePeriodId: (int) $periodB->id
                    )) {
                        $errors[] = 'المعلم الثاني مشغول في توقيت الحصة الأولى';
                    }

                    $classA = $periodA->category?->name ?? '—';
                    $classB = $periodB->category?->name ?? '—';
                    $before = [
                        [
                            'period_id' => $periodA->id,
                            'period_number' => $periodA->period_number,
                            'teacher_id' => $mainA->teacher_id,
                            'teacher_name' => $mainA->teacher?->name,
                            'subject_name' => $mainA->subject?->name,
                            'class_name' => $classA,
                            'label' => ($mainA->subject?->name ?? '—').' — '.$classA,
                        ],
                        [
                            'period_id' => $periodB->id,
                            'period_number' => $periodB->period_number,
                            'teacher_id' => $mainB->teacher_id,
                            'teacher_name' => $mainB->teacher?->name,
                            'subject_name' => $mainB->subject?->name,
                            'class_name' => $classB,
                            'label' => ($mainB->subject?->name ?? '—').' — '.$classB,
                        ],
                    ];
                    $after = [
                        [
                            'period_id' => $periodA->id,
                            'period_number' => $periodA->period_number,
                            'teacher_id' => $mainB->teacher_id,
                            'teacher_name' => $mainB->teacher?->name,
                            'subject_name' => $mainB->subject?->name,
                            'class_name' => $classA,
                            'label' => ($mainB->subject?->name ?? '—').' — '.$classA.' (بديل مؤقت)',
                        ],
                        [
                            'period_id' => $periodB->id,
                            'period_number' => $periodB->period_number,
                            'teacher_id' => $mainA->teacher_id,
                            'teacher_name' => $mainA->teacher?->name,
                            'subject_name' => $mainA->subject?->name,
                            'class_name' => $classB,
                            'label' => ($mainA->subject?->name ?? '—').' — '.$classB.' (بديل مؤقت)',
                        ],
                    ];
                }
            }
        }

        return [
            'swap_type' => $swapType,
            'before' => $before,
            'after' => $after,
            'errors' => $errors,
        ];
    }

    protected function applyMoveToRow(array $row, TimetablePeriod $source, TimetablePeriod $target, ?TimetableAssignment $main, User $teacher): array
    {
        $out = $row;
        $fromNum = (int) $source->period_number;
        $toNum = (int) $target->period_number;
        if ((int) $row['period_number'] === $fromNum) {
            $out['label'] = 'فارغة';
            $out['subject_name'] = null;
            $out['teacher_name'] = null;
        }
        if ((int) $row['period_number'] === $toNum) {
            $out['label'] = ($main?->subject?->name ?? 'حصة').' — '.($source->category?->name ?? '');
            $out['subject_name'] = $main?->subject?->name;
            $out['teacher_name'] = $teacher->name;
            $out['period_id'] = $target->id;
        }

        return $out;
    }

    protected function resolveNewPeriodNumber(array $payload, TimetablePeriod $sourcePeriod): ?int
    {
        if (($payload['swap_type'] ?? '') === 'swap_lessons') {
            $secondary = TimetablePeriod::find($payload['secondary_period_id'] ?? null);

            return $secondary ? (int) $secondary->period_number : null;
        }

        if (!empty($payload['target_period_id'])) {
            return (int) TimetablePeriod::find($payload['target_period_id'])?->period_number;
        }

        return null;
    }

    protected function validateSwapPayload(array $payload, bool $previewOnly = false): void
    {
        $types = array_keys(config('attendance.daily_coverage.swap_types', []));
        if (!in_array($payload['swap_type'] ?? '', $types, true)) {
            throw ValidationException::withMessages(['swap_type' => 'نوع تبديل غير مدعوم']);
        }
    }

    protected function loadDayPeriods(int $timetableId, int $dayId): Collection
    {
        return TimetablePeriod::query()
            ->where('timetable_id', $timetableId)
            ->where('timetable_day_id', $dayId)
            ->where('period_number', '>', 0)
            ->with([
                'category:id,name',
                'assignments' => fn ($q) => $q->where('type', 'main'),
                'assignments.teacher:id,name',
                'assignments.subject:id,name',
            ])
            ->orderBy('period_number')
            ->get();
    }

    protected function teacherDaySchedule(
        User $teacher,
        Collection $dayPeriods,
        Collection $adjustments,
        array $absentIds,
        Carbon $date
    ): array {
        $numbers = $dayPeriods->pluck('period_number')->unique()->sort()->values();
        $rows = [];
        foreach ($numbers as $num) {
            $slot = $this->slotLabelForTeacher($teacher->id, (int) $num, $dayPeriods, $adjustments, $absentIds);
            $rows[] = [
                'period_number' => (int) $num,
                'period_id' => $slot['period_id'],
                'category_id' => $slot['category_id'],
                'label' => $slot['label'],
                'has_lesson' => $slot['has_lesson'],
                'is_free' => $slot['is_free'],
                'subject_name' => $slot['subject_name'],
            ];
        }

        return $rows;
    }

    protected function slotLabelForTeacher(
        int $teacherId,
        int $periodNumber,
        Collection $dayPeriods,
        Collection $adjustments,
        array $absentIds
    ): array {
        foreach ($dayPeriods as $period) {
            if ((int) $period->period_number !== $periodNumber) {
                continue;
            }
            $main = $period->assignments->where('type', 'main')->first();
            if (!$main || (int) $main->teacher_id !== $teacherId) {
                continue;
            }
            if (in_array($teacherId, $absentIds, true)) {
                continue;
            }

            return [
                'period_id' => $period->id,
                'category_id' => $period->category_id,
                'label' => ($main->subject?->name ?? 'حصة').' — '.($period->category?->name ?? ''),
                'has_lesson' => true,
                'is_free' => false,
                'subject_name' => $main->subject?->name,
            ];
        }

        if (!$this->isTeacherBusyAtPeriodEffective($dayPeriods, $periodNumber, $teacherId, $absentIds, $adjustments)) {
            return [
                'period_id' => null,
                'category_id' => null,
                'label' => 'فارغة ✓',
                'has_lesson' => false,
                'is_free' => true,
                'subject_name' => null,
            ];
        }

        return [
            'period_id' => null,
            'category_id' => null,
            'label' => 'مشغول',
            'has_lesson' => false,
            'is_free' => false,
            'subject_name' => null,
        ];
    }

    protected function isTeacherBusyAtPeriodEffective(
        Collection $dayPeriods,
        int $periodNumber,
        int $teacherId,
        array $absentIds,
        Collection $adjustments,
        ?int $excludePeriodId = null
    ): bool {
        foreach ($dayPeriods as $period) {
            if ((int) $period->period_number !== $periodNumber) {
                continue;
            }
            if ($excludePeriodId && (int) $period->id === $excludePeriodId) {
                continue;
            }
            $main = $period->assignments->where('type', 'main')->first();
            if (
                $main
                && (int) $main->teacher_id === $teacherId
                && !in_array($teacherId, $absentIds, true)
            ) {
                return true;
            }
        }

        foreach ($adjustments as $adj) {
            if ($adj->swap_type !== DailyTimetableAdjustment::SWAP_MOVE) {
                continue;
            }
            $target = $adj->targetPeriod;
            if (
                $target
                && (int) $target->period_number === $periodNumber
                && (int) $adj->teacher_id === $teacherId
            ) {
                return true;
            }
        }

        return false;
    }

    protected function findTeacherPeriodAtNumber(
        int $teacherId,
        Collection $dayPeriods,
        int $periodNumber,
        ?int $categoryId
    ): ?TimetablePeriod {
        foreach ($dayPeriods as $period) {
            if ((int) $period->period_number !== $periodNumber) {
                continue;
            }
            if ($categoryId && (int) $period->category_id !== (int) $categoryId) {
                continue;
            }
            $main = $period->assignments->where('type', 'main')->first();
            if ($main && (int) $main->teacher_id === $teacherId) {
                return $period;
            }
        }

        return $dayPeriods->first(
            fn ($p) => (int) $p->period_number === $periodNumber
                && (!$categoryId || (int) $p->category_id === (int) $categoryId)
        );
    }

    public function resolveTargetPeriodForMove(
        int $teacherId,
        Collection $dayPeriods,
        int $targetPeriodNumber,
        int $sourceCategoryId
    ): ?TimetablePeriod {
        $owned = $this->findTeacherPeriodAtNumber($teacherId, $dayPeriods, $targetPeriodNumber, $sourceCategoryId);
        if ($owned) {
            return $owned;
        }

        return $dayPeriods->first(
            fn ($p) => (int) $p->period_number === $targetPeriodNumber
                && (int) $p->category_id === $sourceCategoryId
        );
    }

    protected function scoreSwapCandidate(
        User $teacher,
        TimetablePeriod $triggerPeriod,
        ?int $triggerSubjectId,
        array $coverageBalances,
        Timetable $timetable
    ): int {
        $score = 0;
        $subjectIds = $teacher->subjects->pluck('id')->all();
        if ($triggerSubjectId && in_array($triggerSubjectId, $subjectIds, true)) {
            $score += 80;
        }
        $balance = $coverageBalances[$teacher->id]['total'] ?? 0;
        $score += max(0, 40 - $balance * 4);

        return $score;
    }
}
