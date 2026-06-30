<?php

namespace App\Services;

use App\Models\AttendanceAlert;
use App\Models\Category;
use App\Models\LessonEnrollment;
use App\Models\StudentBehaviorRecord;
use App\Models\StudentEnrollment;
use App\Models\StudentGrade;
use App\Models\StudentStatusHistory;
use App\Models\User;
use App\Models\UserWallet;
use App\Support\Student\GuardianRelationship;
use App\Support\Student\StudentCommandCenterAssembler;
use App\Support\Student\StudentStatus;

class StudentProfileService
{
    protected const GRADES_LIST_LIMIT = 50;

    protected const BEHAVIOR_LIST_LIMIT = 50;

    protected const ATTENDANCE_RECORDS_LIMIT = 50;

    public function __construct(
        protected AttendanceStatsService $attendanceStats,
        protected StudentLifecycleService $lifecycle,
        protected StudentCommandCenterAssembler $commandCenter,
    ) {}

    /**
     * Full read-model payload for the admin student profile hub.
     */
    public function forAdmin(User $student): array
    {
        abort_unless($student->user_type === 'student', 404);

        $student->load([
            'category',
            'currentStudentEnrollment',
            'guardians:id,name,email,phone,national_id',
        ]);

        $attendance = $this->attendanceStats->studentSummary($student->id, null, null, self::ATTENDANCE_RECORDS_LIMIT);
        $grades = $this->gradesSummary($student);
        $behavior = $this->behaviorSummary($student);
        $wallet = $this->walletSummary($student);
        $classInfo = $this->classInformation($student);
        $guardians = $this->guardiansPayload($student);
        $siblings = $this->siblingsFor($student);
        $attendanceRate = $this->attendanceRatePercent($attendance);
        $activeAlert = $this->activeAttendanceAlert($student->id);
        $latestGrade = $grades['items']->first();
        $latestTransaction = collect($wallet['transactions'])->first();

        $enrollments = $this->enrollmentsPayload($student);
        $activityTimeline = $this->activityTimeline($student);

        $overviewPayload = [
            'attendance' => [
                'present' => $attendance['present'],
                'absent' => $attendance['absent'],
                'late' => $attendance['late'],
                'excused' => $attendance['excused'],
                'total' => $attendance['total'],
                'rate_percent' => $attendanceRate,
            ],
            'attendance_rate_percent' => $attendanceRate,
            'last_attendance_date' => $this->lastAttendanceDate($attendance['records']),
            'grades_average' => $grades['average_percent'],
            'grades_count' => $grades['count'],
            'latest_grade' => $latestGrade,
            'behavior' => $behavior['counts'],
            'negative_behavior_count' => $behavior['counts']['negative'],
            'wallet_balance' => $wallet['balance'],
            'latest_wallet_transaction' => $latestTransaction,
            'active_alert' => $activeAlert,
            'enrollments_count' => LessonEnrollment::query()
                ->where('student_id', $student->id)
                ->where('status', 'active')
                ->count(),
        ];

        $commandCenter = $this->commandCenter->assemble(
            $student,
            $guardians['items']->all(),
            $siblings,
            $overviewPayload,
            [
                'summary' => [
                    'present' => $attendance['present'],
                    'absent' => $attendance['absent'],
                    'late' => $attendance['late'],
                    'excused' => $attendance['excused'],
                    'total' => $attendance['total'],
                    'rate_percent' => $attendanceRate,
                ],
                'records' => $attendance['records']->map(fn ($r) => [
                    'id' => $r->id,
                    'attendance_date' => $r->attendance_date?->toDateString(),
                    'session_type' => $r->session_type,
                    'session_label' => $r->session_label,
                    'status' => $r->status,
                    'status_label' => $this->attendanceStatusLabel($r->status),
                    'notes' => $r->notes,
                ])->values()->all(),
            ],
            $grades,
            $behavior,
            $wallet,
            $enrollments,
            $classInfo,
            $activityTimeline,
        );

        $profile = $this->studentPayload($student, $guardians, $classInfo, $attendanceRate, $activeAlert, $enrollments, count($siblings));

        return [
            'profile' => $profile,
            'student_context' => $commandCenter['student_context'],
            'workspace_context' => $this->workspaceContextForStudent($profile),
            'command_center' => $commandCenter,
            'guardians' => $guardians['items'],
            'siblings' => $siblings,
            'classInfo' => $classInfo,
            'enrollments' => $enrollments,
            'overview' => $overviewPayload,
            'attendance' => [
                'summary' => [
                    'present' => $attendance['present'],
                    'absent' => $attendance['absent'],
                    'late' => $attendance['late'],
                    'excused' => $attendance['excused'],
                    'total' => $attendance['total'],
                    'rate_percent' => $attendanceRate,
                ],
                'records' => $attendance['records']->map(fn ($r) => [
                    'id' => $r->id,
                    'attendance_date' => $r->attendance_date?->toDateString(),
                    'session_type' => $r->session_type,
                    'session_label' => $r->session_label,
                    'status' => $r->status,
                    'status_label' => $this->attendanceStatusLabel($r->status),
                    'notes' => $r->notes,
                ])->values(),
            ],
            'grades' => $grades,
            'behavior' => $behavior,
            'wallet' => $wallet,
            'lifecycle' => [
                'actions' => $this->lifecycle->availableActions($student),
                'status_transitions' => StudentStatus::transitionOptions($student->student_status ?? StudentStatus::ACTIVE),
            ],
            'activity_timeline' => $activityTimeline,
        ];
    }

    protected function studentPayload(
        User $student,
        array $guardians,
        ?array $classInfo,
        ?float $attendanceRate,
        ?array $activeAlert,
        array $enrollments = [],
        int $siblingsCount = 0,
    ): array {
        $primaryGuardian = $guardians['primary'];
        $current = $enrollments['current'] ?? null;

        return [
            'id' => $student->id,
            'name' => $student->name,
            'email' => $student->email,
            'phone' => $student->phone,
            'profile_photo_url' => $student->profile_photo_url,
            'student_code' => $student->student_code,
            'first_name' => $student->first_name,
            'father_name' => $student->father_name,
            'grandfather_name' => $student->grandfather_name,
            'date_of_birth' => $student->date_of_birth?->toDateString(),
            'gender' => $student->gender,
            'national_id' => $student->national_id,
            'enrollment_date' => $student->enrollment_date?->toDateString(),
            'category_id' => $student->category_id,
            'category_name' => $student->category?->name,
            'class_path_label' => $classInfo['path_label'] ?? null,
            'status' => $student->student_status ?? StudentStatus::ACTIVE,
            'status_label' => StudentStatus::label($student->student_status),
            'status_badge_class' => StudentStatus::badgeClass($student->student_status),
            'guardians_count' => $guardians['count'],
            'primary_guardian_name' => $primaryGuardian['name'] ?? null,
            'primary_guardian_id' => $primaryGuardian['id'] ?? null,
            'attendance_rate_percent' => $attendanceRate,
            'active_alert' => $activeAlert,
            'created_at' => $student->created_at?->toDateString(),
            'academic_year' => $current['academic_year'] ?? null,
            'enrollment_status' => $current['status'] ?? null,
            'enrollment_status_label' => $current['status_label'] ?? null,
            'siblings_count' => $siblingsCount,
            'source_admission_id' => $current['admission_reference_id'] ?? null,
            'source_admission_url' => $current['admission_profile_url'] ?? null,
        ];
    }

    protected function guardiansPayload(User $student): array
    {
        $items = $student->guardians->map(function (User $g) {
            $pivot = [
                'relationship_type' => $g->pivot->relationship_type ?? 'guardian',
                'is_primary' => (bool) ($g->pivot->is_primary ?? false),
                'is_emergency_contact' => (bool) ($g->pivot->is_emergency_contact ?? false),
                'is_pickup_authorized' => (bool) ($g->pivot->is_pickup_authorized ?? true),
                'is_financial_responsible' => (bool) ($g->pivot->is_financial_responsible ?? false),
            ];

            return [
                'id' => $g->id,
                'name' => $g->name,
                'email' => $g->email,
                'phone' => $g->phone,
                'national_id' => $g->national_id,
                'relationship_type' => $pivot['relationship_type'],
                'relationship_label' => GuardianRelationship::typeLabel($pivot['relationship_type']),
                'is_primary' => $pivot['is_primary'],
                'is_emergency_contact' => $pivot['is_emergency_contact'],
                'is_pickup_authorized' => $pivot['is_pickup_authorized'],
                'is_financial_responsible' => $pivot['is_financial_responsible'],
                'role_labels' => GuardianRelationship::roleLabels($pivot),
            ];
        })->values();

        $primary = $items->firstWhere('is_primary', true) ?? $items->first();

        return [
            'items' => $items,
            'count' => $items->count(),
            'primary' => $primary,
        ];
    }

    protected function enrollmentsPayload(User $student): array
    {
        $records = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->with('performedBy:id,name')
            ->orderByDesc('enrollment_date')
            ->orderByDesc('id')
            ->get();

        $current = $records->firstWhere('is_current', true);

        return [
            'current' => $current ? $this->enrollmentItem($current) : null,
            'timeline' => $records->map(fn (StudentEnrollment $e) => $this->enrollmentItem($e))->values(),
        ];
    }

    protected function enrollmentItem(StudentEnrollment $enrollment): array
    {
        $admissionId = $enrollment->admission_reference_id;

        return [
            'id' => $enrollment->id,
            'admission_reference_id' => $admissionId,
            'admission_profile_url' => $admissionId
                ? route('admin.admissions.show', $admissionId)
                : null,
            'academic_year' => $enrollment->academic_year,
            'stage_name' => $enrollment->stage_name,
            'grade_name' => $enrollment->grade_name,
            'class_name' => $enrollment->class_name,
            'path_label' => collect([$enrollment->stage_name, $enrollment->grade_name, $enrollment->class_name])
                ->filter()
                ->implode(' / '),
            'enrollment_date' => $enrollment->enrollment_date?->toDateString(),
            'promotion_date' => $enrollment->promotion_date?->toDateString(),
            'withdrawal_date' => $enrollment->withdrawal_date?->toDateString(),
            'status' => $enrollment->status,
            'status_label' => $this->enrollmentStatusLabel($enrollment->status),
            'action_type' => $enrollment->action_type,
            'action_type_label' => $this->enrollmentActionLabel($enrollment->action_type),
            'reason' => $enrollment->reason,
            'notes' => $enrollment->notes,
            'is_current' => $enrollment->is_current,
            'source' => $enrollment->source,
            'performed_by' => $enrollment->performedBy?->name,
        ];
    }

    protected function enrollmentStatusLabel(?string $status): string
    {
        return match ($status) {
            'active' => 'نشط',
            'completed' => 'مكتمل',
            'transferred' => 'محوّل',
            'withdrawn' => 'منسحب',
            default => $status ?? '—',
        };
    }

    protected function enrollmentActionLabel(?string $action): string
    {
        return match ($action) {
            'initial' => 'قيد أولي',
            'promotion' => 'ترقية',
            'transfer' => 'نقل',
            'withdrawal' => 'انسحاب',
            're_enrollment' => 'إعادة قيد',
            'admission' => 'قبول',
            'graduation' => 'تخرج',
            default => $action ?? '—',
        };
    }

    protected function activityTimeline(User $student): array
    {
        $enrollmentEvents = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->with('performedBy:id,name')
            ->get()
            ->map(fn (StudentEnrollment $e) => [
                'id' => 'enrollment-' . $e->id,
                'type' => 'enrollment',
                'action_type' => $e->action_type,
                'title' => $this->enrollmentActionLabel($e->action_type),
                'subtitle' => collect([$e->stage_name, $e->grade_name, $e->class_name])->filter()->implode(' / '),
                'occurred_at' => ($e->enrollment_date ?? $e->created_at)?->toDateTimeString(),
                'reason' => $e->reason,
                'notes' => $e->notes,
                'performed_by' => $e->performedBy?->name,
                'badge_class' => 'bg-primary',
            ]);

        $statusEvents = StudentStatusHistory::query()
            ->where('student_id', $student->id)
            ->with('changedBy:id,name')
            ->get()
            ->map(fn (StudentStatusHistory $h) => [
                'id' => 'status-' . $h->id,
                'type' => 'status',
                'action_type' => 'status_change',
                'title' => 'تغيير الحالة',
                'subtitle' => (StudentStatus::label($h->from_status) ?: '—') . ' ← ' . StudentStatus::label($h->to_status),
                'occurred_at' => ($h->effective_at ?? $h->created_at)?->toDateTimeString(),
                'reason' => $h->reason,
                'notes' => $h->notes,
                'performed_by' => $h->changedBy?->name,
                'badge_class' => StudentStatus::badgeClass($h->to_status),
            ]);

        return $enrollmentEvents
            ->merge($statusEvents)
            ->sortByDesc('occurred_at')
            ->values()
            ->all();
    }

    protected function siblingsFor(User $student): array
    {
        $guardianIds = $student->guardians()->pluck('users.id');

        if ($guardianIds->isEmpty()) {
            return [];
        }

        return User::query()
            ->where('user_type', 'student')
            ->where('id', '!=', $student->id)
            ->whereHas('guardians', fn ($q) => $q->whereIn('users.id', $guardianIds))
            ->with(['category:id,name', 'currentStudentEnrollment'])
            ->orderBy('name')
            ->get(['id', 'name', 'student_code', 'category_id', 'student_status'])
            ->map(fn (User $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'student_code' => $s->student_code,
                'category_name' => $s->category?->name,
                'grade_label' => $s->currentStudentEnrollment?->grade_name ?? $s->category?->name,
                'status' => $s->student_status,
                'status_label' => StudentStatus::label($s->student_status),
                'status_badge_class' => StudentStatus::badgeClass($s->student_status),
                'profile_url' => route('admin.students.show', $s->id),
            ])
            ->values()
            ->all();
    }

    protected function classInformation(User $student): ?array
    {
        if (! $student->category_id) {
            return null;
        }

        $path = $this->categoryPath($student->category_id);
        $leaf = end($path) ?: null;

        $subjects = Category::query()
            ->find($student->category_id)
            ?->subjects()
            ->orderBy('name')
            ->get(['subjects.id', 'subjects.name'])
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])
            ->values() ?? collect();

        return [
            'category_id' => $student->category_id,
            'name' => $leaf['name'] ?? $student->category?->name,
            'path' => $path,
            'path_label' => collect($path)->pluck('name')->implode(' / '),
            'subjects' => $subjects,
            'subjects_count' => $subjects->count(),
        ];
    }

    protected function categoryPath(int $categoryId): array
    {
        $path = [];
        $currentId = $categoryId;
        $guard = 0;

        while ($currentId && $guard < 12) {
            $node = Category::query()->find($currentId);
            if (! $node) {
                break;
            }

            array_unshift($path, [
                'id' => $node->id,
                'name' => $node->name,
            ]);

            $currentId = $node->parent_id;
            $guard++;
        }

        return $path;
    }

    protected function gradesSummary(User $student): array
    {
        $baseQuery = StudentGrade::query()->where('student_id', $student->id);
        $totalCount = (clone $baseQuery)->count();

        $grades = (clone $baseQuery)
            ->with('subject:id,name')
            ->orderByDesc('assessed_at')
            ->limit(self::GRADES_LIST_LIMIT)
            ->get();

        $average = $totalCount === 0
            ? null
            : round((float) (clone $baseQuery)
                ->selectRaw('AVG(CASE WHEN max_score > 0 THEN (score / max_score) * 100 ELSE NULL END) as avg_pct')
                ->value('avg_pct'), 1);

        return [
            'count' => $totalCount,
            'average_percent' => $average,
            'limited' => $totalCount > self::GRADES_LIST_LIMIT,
            'items' => $grades->map(fn ($g) => [
                'id' => $g->id,
                'title' => $g->title,
                'subject' => $g->subject?->name,
                'assessment_type' => $g->assessment_type,
                'term_label' => $g->term_label,
                'score' => $g->score,
                'max_score' => $g->max_score,
                'percentage' => $g->percentage(),
                'assessed_at' => $g->assessed_at?->toDateString(),
                'notes' => $g->notes,
            ])->values(),
        ];
    }

    protected function behaviorSummary(User $student): array
    {
        $baseQuery = StudentBehaviorRecord::query()->where('student_id', $student->id);

        $records = (clone $baseQuery)
            ->with('recordedBy:id,name')
            ->orderByDesc('occurred_at')
            ->limit(self::BEHAVIOR_LIST_LIMIT)
            ->get();

        $totalCount = (clone $baseQuery)->count();

        return [
            'counts' => [
                'positive' => (clone $baseQuery)->where('severity', 'positive')->count(),
                'neutral' => (clone $baseQuery)->where('severity', 'neutral')->count(),
                'negative' => (clone $baseQuery)->where('severity', 'negative')->count(),
            ],
            'limited' => $totalCount > self::BEHAVIOR_LIST_LIMIT,
            'items' => $records->map(fn ($r) => [
                'id' => $r->id,
                'severity' => $r->severity,
                'category' => $r->category,
                'title' => $r->title,
                'description' => $r->description,
                'occurred_at' => $r->occurred_at?->toDateString(),
                'recorded_by' => $r->recordedBy?->name,
            ])->values(),
        ];
    }

    protected function walletSummary(User $student): array
    {
        $wallet = UserWallet::query()->where('user_id', $student->id)->first();

        if (! $wallet) {
            return [
                'balance' => 0,
                'total_credited' => 0,
                'total_debited' => 0,
                'transactions' => [],
            ];
        }

        $wallet->load(['transactions' => fn ($q) => $q->orderByDesc('created_at')->limit(20)]);

        return [
            'balance' => (float) $wallet->balance,
            'total_credited' => (float) $wallet->total_credited,
            'total_debited' => (float) $wallet->total_debited,
            'transactions' => $wallet->transactions->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'amount' => (float) $t->amount,
                'description' => $t->description,
                'created_at' => $t->created_at?->toDateTimeString(),
            ])->values(),
        ];
    }

    protected function activeAttendanceAlert(int $studentId): ?array
    {
        $alert = AttendanceAlert::query()
            ->where('student_id', $studentId)
            ->whereNull('acknowledged_at')
            ->latest('triggered_at')
            ->first();

        if (! $alert) {
            return null;
        }

        return [
            'level' => $alert->level,
            'absences_count' => $alert->absences_count,
            'triggered_at' => $alert->triggered_at?->toDateString(),
        ];
    }

    protected function attendanceRatePercent(array $attendance): ?float
    {
        $total = $attendance['total'] ?? 0;

        if ($total === 0) {
            return null;
        }

        $attended = ($attendance['present'] ?? 0) + ($attendance['late'] ?? 0);

        return round($attended / $total * 100, 1);
    }

    protected function lastAttendanceDate($records): ?string
    {
        $latest = $records->first();

        return $latest?->attendance_date?->toDateString();
    }

    protected function attendanceStatusLabel(?string $status): string
    {
        return match ($status) {
            'present' => 'حاضر',
            'absent' => 'غائب',
            'late' => 'متأخر',
            'excused' => 'بعذر',
            default => $status ?? '—',
        };
    }

    /**
     * Navigation context for UX-7 workspace switching (no business logic).
     *
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    protected function workspaceContextForStudent(array $profile): array
    {
        $guardianId = $profile['primary_guardian_id'] ?? null;
        $guardianName = $profile['primary_guardian_name'] ?? null;

        return [
            'mode' => 'student',
            'entity_id' => $profile['id'] ?? null,
            'entity_name' => $profile['name'] ?? '—',
            'label' => 'Student Workspace',
            'label_ar' => 'مساحة الطالب',
            'icon' => 'bi-mortarboard-fill',
            'return_url' => route('admin.students.index'),
            'return_label' => 'Return To Admin',
            'return_label_ar' => 'العودة للوحة الإدارة',
            'related_profile_url' => $guardianId ? route('admin.parents.show', $guardianId) : null,
            'related_profile_label' => $guardianName
                ? "Open Family — {$guardianName}"
                : 'Open Family Profile',
            'related_profile_label_ar' => $guardianName
                ? "فتح العائلة — {$guardianName}"
                : 'فتح ملف العائلة',
        ];
    }
}
