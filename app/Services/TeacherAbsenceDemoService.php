<?php

namespace App\Services;

use App\Models\Subject;
use App\Models\TeacherAttendance;
use App\Models\Timetable;
use App\Models\TimetableAssignment;
use App\Models\TimetableDay;
use App\Models\TimetablePeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TeacherAbsenceDemoService
{
    public const DEMO_NOTES_MARKER = 'TeacherAbsenceDemoSeeder';

    public function __construct(
        private DailyAbsenceCoverageService $coverageService
    ) {}

    public function hasDemoDataForToday(?string $date = null): bool
    {
        $date = $date ?? Carbon::today()->toDateString();

        return TeacherAttendance::query()
            ->whereDate('attendance_date', $date)
            ->where('notes', 'like', '%'.self::DEMO_NOTES_MARKER.'%')
            ->exists();
    }

    /**
     * @return array{success: bool, already_exists?: bool, message: string, preview?: array}
     */
    public function seedForToday(?string $date = null): array
    {
        $today = Carbon::parse($date ?? Carbon::today()->toDateString())->startOfDay();

        if ($this->hasDemoDataForToday($today->toDateString())) {
            return [
                'success' => false,
                'already_exists' => true,
                'message' => 'تم إنشاء بيانات تجريبية مسبقاً',
                'preview' => $this->coverageService->buildPreview($today->toDateString()),
            ];
        }

        $dayName = $this->coverageService->arabicDayNameForDate($today);

        $timetable = Timetable::query()
            ->withCount(['periods as periods_count'])
            ->orderByDesc('periods_count')
            ->orderByDesc('id')
            ->first();

        if (! $timetable) {
            return [
                'success' => false,
                'message' => 'لا يوجد جدول دراسي — شغّل إعداد الجدول أولاً.',
            ];
        }

        $day = TimetableDay::query()
            ->where('timetable_id', $timetable->id)
            ->where('day_name', $dayName)
            ->first();

        if (! $day) {
            return [
                'success' => false,
                'message' => "لا يوجد يوم «{$dayName}» في الجدول.",
            ];
        }

        $admin = User::query()->where('user_type', 'admin')->first();

        $this->enhanceTeacherProfiles();
        $this->ensureTimetableSettings($timetable, $dayName);
        $this->ensureDemoAssignmentsForToday($timetable->id, $day->id);

        $teachersOnDuty = $this->teachersWithLessonsOnDay($day->id);

        if ($teachersOnDuty->isEmpty()) {
            return [
                'success' => false,
                'message' => 'لا يوجد معلمون لديهم حصص اليوم في الجدول.',
            ];
        }

        DB::transaction(function () use ($today, $admin, $teachersOnDuty) {
            TeacherAttendance::query()
                ->whereDate('attendance_date', $today)
                ->where('notes', 'like', '%'.self::DEMO_NOTES_MARKER.'%')
                ->delete();

            $absencePlan = $this->buildAbsencePlan($teachersOnDuty);

            foreach ($absencePlan as $row) {
                TeacherAttendance::updateOrCreate(
                    [
                        'teacher_id' => $row['teacher_id'],
                        'attendance_date' => $today->toDateString(),
                    ],
                    [
                        'status' => $row['status'],
                        'reason' => $row['reason'],
                        'source' => 'system',
                        'recorded_by' => $admin?->id,
                        'notes' => self::DEMO_NOTES_MARKER.' — تجربة مركز التغطية',
                    ]
                );
            }
        });

        $preview = $this->coverageService->buildPreview($today->toDateString());

        return [
            'success' => true,
            'message' => 'تم إنشاء بيانات غياب تجريبية بنجاح',
            'preview' => $preview,
        ];
    }

    protected function ensureDemoAssignmentsForToday(int $timetableId, int $dayId): void
    {
        $admin = User::query()->where('user_type', 'admin')->first();
        $math = Subject::query()->where('name', 'الرياضيات')->first();
        $arabic = Subject::query()->where('name', 'اللغة العربية')->first();

        $muhammad = User::query()->where('email', 'teacher1@eduvera.test')->where('user_type', 'teacher')->first();
        $heba = User::query()->where('email', 'teacher8@eduvera.test')->where('user_type', 'teacher')->first();
        $ahmed = User::query()->where('email', 'teacher2@eduvera.test')->where('user_type', 'teacher')->first();

        if (! $muhammad || ! $math) {
            return;
        }

        $anchor = TimetablePeriod::query()
            ->where('timetable_id', $timetableId)
            ->where('timetable_day_id', $dayId)
            ->where('period_number', '>', 0)
            ->orderBy('period_number')
            ->orderBy('id')
            ->first();

        if (! $anchor) {
            return;
        }

        $periods = TimetablePeriod::query()
            ->where('timetable_id', $timetableId)
            ->where('timetable_day_id', $dayId)
            ->where('category_id', $anchor->category_id)
            ->whereIn('period_number', [2, 3, 4])
            ->orderBy('period_number')
            ->get();

        if ($periods->isEmpty()) {
            $periods = TimetablePeriod::query()
                ->where('timetable_id', $timetableId)
                ->where('timetable_day_id', $dayId)
                ->where('period_number', '>', 0)
                ->orderBy('period_number')
                ->limit(3)
                ->get();
        }

        foreach ($periods as $i => $period) {
            $subject = $i === 0 ? $math : ($arabic ?? $math);
            $teacherId = $muhammad->id;

            if ($i === 1 && $heba) {
                $teacherId = $heba->id;
                $subject = $math;
            }

            TimetableAssignment::updateOrCreate(
                [
                    'timetable_period_id' => $period->id,
                    'type' => 'main',
                ],
                [
                    'teacher_id' => $teacherId,
                    'subject_id' => $subject->id,
                    'assigned_by' => $admin?->id,
                    'status' => 'approved',
                ]
            );
        }

        if ($ahmed && $periods->count() > 0) {
            $extra = TimetablePeriod::query()
                ->where('timetable_id', $timetableId)
                ->where('timetable_day_id', $dayId)
                ->where('period_number', 5)
                ->where('category_id', '!=', $anchor->category_id)
                ->orderBy('id')
                ->first();

            if ($extra) {
                TimetableAssignment::updateOrCreate(
                    [
                        'timetable_period_id' => $extra->id,
                        'type' => 'main',
                    ],
                    [
                        'teacher_id' => $ahmed->id,
                        'subject_id' => $math->id,
                        'assigned_by' => $admin?->id,
                        'status' => 'approved',
                    ]
                );
            }
        }
    }

    protected function enhanceTeacherProfiles(): void
    {
        $math = Subject::query()->where('name', 'الرياضيات')->first();
        if (! $math) {
            return;
        }

        $profiles = [
            ['email' => 'teacher1@eduvera.test', 'department' => 'قسم الرياضيات — إعدادي', 'job_title' => 'معلم رياضيات'],
            ['email' => 'teacher8@eduvera.test', 'department' => 'قسم الرياضيات — إعدادي', 'job_title' => 'معلمة رياضيات'],
            ['email' => 'teacher2@eduvera.test', 'department' => 'قسم العلوم — إعدادي', 'job_title' => 'معلم علوم'],
        ];

        foreach ($profiles as $p) {
            $teacher = User::query()->where('email', $p['email'])->where('user_type', 'teacher')->first();
            if (! $teacher) {
                continue;
            }
            $teacher->update([
                'department' => $p['department'],
                'job_title' => $p['job_title'],
            ]);
            $teacher->subjects()->syncWithoutDetaching([$math->id]);
        }
    }

    protected function ensureTimetableSettings(Timetable $timetable, string $dayName): void
    {
        $settings = $timetable->settings ?? [];
        $settings['working_days'] = $settings['working_days'] ?? [
            'الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس',
        ];
        $settings['selected_stages'] = $settings['selected_stages'] ?? ['middle'];
        $settings['educational_stage'] = $settings['educational_stage'] ?? 'middle';
        $settings['coverage_balances'] = $settings['coverage_balances'] ?? $this->sampleCoverageBalances();
        $timetable->update(['settings' => $settings]);
    }

    protected function sampleCoverageBalances(): array
    {
        $balances = [];
        $teachers = User::query()->ofType('teacher')->limit(8)->pluck('id');

        foreach ($teachers as $i => $id) {
            $balances[$id] = [
                'week' => $i % 3,
                'month' => $i * 2,
                'total' => $i * 3,
            ];
        }

        return $balances;
    }

    protected function teachersWithLessonsOnDay(int $dayId)
    {
        return TimetableAssignment::query()
            ->where('type', 'main')
            ->whereHas('period', fn ($q) => $q->where('timetable_day_id', $dayId)->where('period_number', '>', 0))
            ->with(['teacher:id,name,email,department', 'subject:id,name'])
            ->get()
            ->groupBy('teacher_id')
            ->map(function ($assignments, $teacherId) {
                $teacher = $assignments->first()->teacher;
                $subjects = $assignments->pluck('subject.name')->unique()->filter()->values();

                return [
                    'teacher_id' => (int) $teacherId,
                    'name' => $teacher?->name,
                    'email' => $teacher?->email,
                    'lessons_today' => $assignments->count(),
                    'subjects' => $subjects->join('، '),
                ];
            })
            ->sortByDesc('lessons_today')
            ->values();
    }

    protected function buildAbsencePlan($teachersOnDuty): array
    {
        $reasons = [
            'sick_leave' => 'إجازة مرضية',
            'absent' => 'غياب بدون عذر',
            'emergency_leave' => 'ظرف طارئ',
        ];

        $demoEmails = [
            ['email' => 'teacher1@eduvera.test', 'status' => 'sick_leave', 'reason' => $reasons['sick_leave']],
            ['email' => 'teacher2@eduvera.test', 'status' => 'absent', 'reason' => $reasons['absent']],
        ];

        $plan = [];
        foreach ($demoEmails as $row) {
            $teacher = User::query()->where('email', $row['email'])->where('user_type', 'teacher')->first();
            if (! $teacher) {
                continue;
            }
            $lessons = $teachersOnDuty->firstWhere('teacher_id', $teacher->id)['lessons_today'] ?? 0;
            if ($lessons < 1) {
                continue;
            }
            $plan[] = [
                'teacher_id' => $teacher->id,
                'status' => $row['status'],
                'reason' => $row['reason'],
            ];
        }

        if ($plan === []) {
            $eligible = $teachersOnDuty->filter(fn ($t) => ($t['lessons_today'] ?? 0) > 0)->take(2);
            foreach ($eligible as $i => $t) {
                $plan[] = [
                    'teacher_id' => $t['teacher_id'],
                    'status' => $i === 0 ? 'sick_leave' : 'absent',
                    'reason' => $i === 0 ? $reasons['sick_leave'] : $reasons['absent'],
                ];
            }
        }

        return $plan;
    }
}
