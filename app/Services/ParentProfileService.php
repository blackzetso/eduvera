<?php

namespace App\Services;

use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionContact;
use App\Models\AttendanceAlert;
use App\Models\StudentBehaviorRecord;
use App\Models\StudentEnrollment;
use App\Models\StudentGrade;
use App\Models\StudentStatusHistory;
use App\Models\User;
use App\Models\UserWallet;
use App\Modules\Canteen\Services\CanteenFinanceBridgeService;
use App\Support\Parent\FamilyCommandCenterAssembler;
use App\Support\Student\StudentStatus;

class ParentProfileService
{
    public function __construct(
        protected AttendanceStatsService $attendanceStats,
        protected FamilyCommandCenterAssembler $commandCenter,
        protected CanteenFinanceBridgeService $canteenFinance,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forAdminShow(User $guardian): array
    {
        abort_unless($guardian->user_type === 'guardian', 404);

        $guardian->load([
            'students.category',
            'students.currentStudentEnrollment',
            'wallet',
        ]);

        $children = $this->childrenPayload($guardian);
        $familyGuardians = $this->familyGuardians($guardian);
        $admissionFollowUps = $this->admissionFollowUps($guardian);
        $timelineEvents = $this->familyTimeline($guardian, $children);
        $profile = $this->profilePayload($guardian, $children, $familyGuardians, $admissionFollowUps);

        $assembled = $this->commandCenter->assemble(
            $profile,
            $children,
            $familyGuardians,
            $admissionFollowUps,
            $timelineEvents,
        );

        return [
            'profile' => $profile,
            'family_context' => $assembled['family_context'],
            'workspace_context' => $this->workspaceContextForFamily($profile, $children),
            'command_center' => $assembled,
            'children' => $children,
            'children_summary' => [
                'count' => count($children),
                'active' => collect($children)->where('status', StudentStatus::ACTIVE)->count(),
            ],
            'guardians' => $familyGuardians,
            'finance_snapshot' => $assembled['finance_snapshot'],
            'attendance_snapshot' => $assembled['attendance_snapshot'],
            'academic_snapshot' => $assembled['academic_snapshot'],
            'risk_summary' => [
                'count' => count($assembled['risks']),
                'items' => $assembled['risks'],
            ],
            'timeline_preview' => $assembled['timeline_preview'],
            'timeline_events' => $timelineEvents,
            'audit_timeline' => app(\App\Services\PlatformAuditService::class)->timelineForSubject($guardian, 20),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @param  array<int, array<string, mixed>>  $familyGuardians
     * @param  array<int, array<string, mixed>>  $admissionFollowUps
     * @return array<string, mixed>
     */
    protected function profilePayload(
        User $guardian,
        array $children,
        array $familyGuardians,
        array $admissionFollowUps,
    ): array {
        $wallet = $guardian->wallet;
        $familyWallet = (float) ($wallet?->balance ?? 0);
        $outstanding = 0.0;

        foreach ($children as $child) {
            $familyWallet += (float) ($child['finance']['wallet_balance'] ?? 0);
            $outstanding += (float) ($child['finance']['outstanding_balance'] ?? 0);
        }

        return [
            'id' => $guardian->id,
            'name' => $guardian->name,
            'email' => $guardian->email,
            'phone' => $guardian->phone,
            'national_id' => $guardian->national_id,
            'job_title' => $guardian->job_title,
            'profile_photo_url' => $guardian->profile_photo_url,
            'parent_code' => $guardian->national_id ? 'GRD-' . $guardian->national_id : 'GRD-' . $guardian->id,
            'status' => 'active',
            'status_label' => 'نشط',
            'status_badge_class' => 'bg-success',
            'role_label' => 'ولي أمر',
            'family_label' => 'عائلة',
            'children_count' => count($children),
            'guardians_count' => count($familyGuardians),
            'family_wallet_balance' => round($familyWallet, 2),
            'outstanding_balance' => round($outstanding, 2),
            'wallet_balance' => (float) ($wallet?->balance ?? 0),
            'pending_admissions' => $admissionFollowUps,
            'created_at' => $guardian->created_at?->toDateString(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function childrenPayload(User $guardian): array
    {
        $students = $guardian->students->sortBy('name')->values();
        $metrics = $this->batchChildMetrics($students->pluck('id')->all());

        return $students
            ->map(fn (User $student) => $this->childCard($student, $metrics[$student->id] ?? []))
            ->values()
            ->all();
    }

    /**
     * @param  array<int>  $studentIds
     * @return array<int, array<string, mixed>>
     */
    protected function batchChildMetrics(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $from = now()->startOfYear()->toDateString();
        $to = now()->toDateString();

        $attendanceRows = \App\Models\StudentAttendance::query()
            ->whereIn('student_id', $studentIds)
            ->whereBetween('attendance_date', [$from, $to])
            ->where('session_type', '!=', 'live_stream')
            ->get()
            ->groupBy('student_id');

        $grades = \App\Models\StudentGrade::query()
            ->whereIn('student_id', $studentIds)
            ->orderByDesc('assessed_at')
            ->get()
            ->groupBy('student_id');

        $wallets = \App\Models\UserWallet::query()
            ->whereIn('user_id', $studentIds)
            ->with(['transactions' => fn ($q) => $q->orderByDesc('created_at')->limit(20)])
            ->get()
            ->keyBy('user_id');

        $behavior = \App\Models\StudentBehaviorRecord::query()
            ->whereIn('student_id', $studentIds)
            ->selectRaw('student_id, severity, count(*) as total')
            ->groupBy('student_id', 'severity')
            ->get()
            ->groupBy('student_id');

        $alerts = AttendanceAlert::query()
            ->whereIn('student_id', $studentIds)
            ->whereNull('acknowledged_at')
            ->orderByDesc('triggered_at')
            ->get()
            ->unique('student_id')
            ->keyBy('student_id');

        $metrics = [];
        foreach ($studentIds as $id) {
            $records = $attendanceRows->get($id, collect());
            $metrics[$id] = [
                'attendance' => [
                    'total' => $records->count(),
                    'present' => $records->where('status', 'present')->count(),
                    'absent' => $records->where('status', 'absent')->count(),
                    'late' => $records->where('status', 'late')->count(),
                    'excused' => $records->where('status', 'excused')->count(),
                    'records' => $records,
                ],
                'grades' => $this->gradesSummaryFromCollection($grades->get($id, collect())),
                'wallet' => $this->walletSummaryFromModel($wallets->get($id)),
                'behavior' => $this->behaviorSummaryFromCollection($behavior->get($id, collect())),
                'alert' => $alerts->get($id) ? [
                    'level' => $alerts->get($id)->level,
                    'triggered_at' => $alerts->get($id)->triggered_at?->toDateString(),
                ] : null,
            ];
        }

        return $metrics;
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $preloaded
     */
    protected function childCard(User $student, array $preloaded = []): array
    {
        $attendance = $preloaded['attendance'] ?? $this->attendanceStats->studentSummary($student->id);
        $rate = $this->attendanceRate($attendance);
        $alert = $preloaded['alert'] ?? $this->activeAttendanceAlert($student->id);
        $grades = $preloaded['grades'] ?? $this->gradesSummary($student);
        $wallet = $preloaded['wallet'] ?? $this->walletSummary($student);
        $behavior = $preloaded['behavior'] ?? $this->behaviorSummary($student);

        $balance = (float) ($wallet['balance'] ?? 0);
        $credited = (float) ($wallet['total_credited'] ?? 0);
        $debited = (float) ($wallet['total_debited'] ?? 0);

        return [
            'id' => $student->id,
            'name' => $student->name,
            'student_code' => $student->student_code,
            'profile_photo_url' => $student->profile_photo_url,
            'email' => $student->email,
            'grade_label' => $student->currentStudentEnrollment?->grade_name ?? $student->category?->name,
            'category_name' => $student->category?->name,
            'category_path' => $student->category?->name,
            'status' => $student->student_status ?? StudentStatus::ACTIVE,
            'status_label' => StudentStatus::label($student->student_status),
            'status_badge_class' => StudentStatus::badgeClass($student->student_status),
            'profile_url' => route('admin.students.show', $student->id),
            'attendance' => [
                'rate_percent' => $rate,
                'present' => $attendance['present'],
                'absent' => $attendance['absent'],
                'late' => $attendance['late'],
                'active_alert' => (bool) $alert,
                'alert_triggered_at' => $alert['triggered_at'] ?? null,
            ],
            'academic' => [
                'average_percent' => $grades['average_percent'],
                'recent' => collect($grades['items'])->take(3)->values()->all(),
            ],
            'finance' => [
                'wallet_balance' => $balance,
                'outstanding_balance' => max(0, $debited - $credited),
                'paid_this_year' => $credited,
                'canteen_spent_today' => (float) $this->canteenFinance->spentTodayForStudent($student->id),
                'installment_status' => $balance < 0 ? 'overdue' : ($balance < 50 ? 'due_soon' : 'current'),
                'installment_status_label' => match (true) {
                    $balance < 0 => 'متأخر',
                    $balance < 50 => 'قريب الاستحقاق',
                    default => 'منتظم',
                },
                'finance_status' => $balance < 0 ? 'red' : ($balance < 50 ? 'amber' : 'green'),
            ],
            'behavior' => $behavior,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function familyGuardians(User $guardian): array
    {
        $studentIds = $guardian->students()->pluck('users.id');

        if ($studentIds->isEmpty()) {
            return [[
                'id' => $guardian->id,
                'name' => $guardian->name,
                'email' => $guardian->email,
                'phone' => $guardian->phone,
                'is_current' => true,
                'profile_url' => route('admin.parents.show', $guardian->id),
            ]];
        }

        return User::query()
            ->where('user_type', 'guardian')
            ->whereHas('students', fn ($q) => $q->whereIn('users.id', $studentIds))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'national_id'])
            ->map(fn (User $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'email' => $g->email,
                'phone' => $g->phone,
                'is_current' => $g->id === $guardian->id,
                'profile_url' => route('admin.parents.show', $g->id),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function admissionFollowUps(User $guardian): array
    {
        $contacts = AdmissionContact::query()
            ->where(function ($q) use ($guardian) {
                $q->where('matched_guardian_id', $guardian->id);
                if ($guardian->email) {
                    $q->orWhere('email', $guardian->email);
                }
                if ($guardian->phone) {
                    $q->orWhere('phone', $guardian->phone);
                }
            })
            ->with(['application.primaryApplicant'])
            ->get();

        return $contacts
            ->filter(fn (AdmissionContact $c) => $c->application && ! $c->application->converted_student_id)
            ->map(fn (AdmissionContact $c) => [
                'id' => $c->application_id,
                'reference_code' => $c->application?->reference_code,
                'applicant_name' => $c->application?->primaryApplicant?->first_name,
                'pipeline_stage' => $c->application?->pipeline_stage,
                'message' => 'طلب قبول مفتوح: ' . ($c->application?->reference_code ?? '—'),
                'date' => $c->application?->updated_at?->toDateString(),
                'profile_url' => $c->application_id
                    ? route('admin.admissions.show', $c->application_id)
                    : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @return array<int, array<string, mixed>>
     */
    protected function familyTimeline(User $guardian, array $children): array
    {
        $events = collect();
        $studentIds = collect($children)->pluck('id');
        $preloadedAttendance = \App\Models\StudentAttendance::query()
            ->whereIn('student_id', $studentIds->all())
            ->orderByDesc('attendance_date')
            ->get()
            ->groupBy('student_id');

        foreach ($children as $child) {
            $events = $events->merge(
                StudentEnrollment::query()
                    ->where('student_id', $child['id'])
                    ->get()
                    ->map(fn (StudentEnrollment $e) => [
                        'id' => 'enrollment-' . $e->id,
                        'type' => 'enrollment',
                        'title' => 'قيد — ' . $child['name'],
                        'subtitle' => collect([$e->stage_name, $e->grade_name])->filter()->implode(' / '),
                        'occurred_at' => ($e->enrollment_date ?? $e->created_at)?->toDateTimeString(),
                        'student_id' => $child['id'],
                        'student_name' => $child['name'],
                        'icon' => 'bi-journal-plus',
                    ])
            );

            $events = $events->merge(
                StudentStatusHistory::query()
                    ->where('student_id', $child['id'])
                    ->get()
                    ->map(fn ($h) => [
                        'id' => 'status-' . $h->id,
                        'type' => 'status',
                        'title' => 'تغيير حالة — ' . $child['name'],
                        'subtitle' => StudentStatus::label($h->to_status),
                        'occurred_at' => ($h->effective_at ?? $h->created_at)?->toDateTimeString(),
                        'student_id' => $child['id'],
                        'student_name' => $child['name'],
                        'icon' => 'bi-toggle-on',
                    ])
            );

            $events = $events->merge(
                StudentBehaviorRecord::query()
                    ->where('student_id', $child['id'])
                    ->orderByDesc('occurred_at')
                    ->limit(3)
                    ->get()
                    ->map(fn ($r) => [
                        'id' => 'behavior-' . $r->id,
                        'type' => 'behavior',
                        'title' => $r->title,
                        'subtitle' => $child['name'],
                        'occurred_at' => $r->occurred_at?->toDateTimeString(),
                        'student_id' => $child['id'],
                        'student_name' => $child['name'],
                        'icon' => 'bi-emoji-smile',
                    ])
            );
        }

        $wallets = UserWallet::query()
            ->whereIn('user_id', $studentIds->merge([$guardian->id])->unique()->all())
            ->with(['transactions' => fn ($q) => $q->orderByDesc('created_at')->limit(3)])
            ->get();

        foreach ($wallets as $wallet) {
            foreach ($wallet->transactions as $tx) {
                $child = collect($children)->firstWhere('id', $wallet->user_id);
                $events->push([
                    'id' => 'wallet-' . $tx->id,
                    'type' => 'wallet',
                    'title' => 'حركة محفظة',
                    'subtitle' => $child['name'] ?? $guardian->name,
                    'occurred_at' => $tx->created_at?->toDateTimeString(),
                    'student_id' => $child['id'] ?? null,
                    'student_name' => $child['name'] ?? $guardian->name,
                    'icon' => 'bi-wallet2',
                ]);
            }
        }

        foreach ($this->admissionFollowUps($guardian) as $admission) {
            $events->push([
                'id' => 'admission-' . $admission['id'],
                'type' => 'admission',
                'title' => 'طلب قبول',
                'subtitle' => $admission['reference_code'] ?? $admission['message'],
                'occurred_at' => $admission['date'] ? $admission['date'] . ' 00:00:00' : null,
                'student_id' => null,
                'student_name' => $admission['applicant_name'],
                'profile_url' => $admission['profile_url'] ?? null,
                'icon' => 'bi-file-earmark-person',
            ]);
        }

        foreach ($children as $child) {
            $records = $preloadedAttendance[$child['id']] ?? collect();
            foreach ($records->take(5) as $record) {
                $events->push([
                    'id' => 'attendance-'.$record->id,
                    'type' => 'attendance',
                    'title' => 'حضور — '.$child['name'],
                    'subtitle' => $this->attendanceStatusLabel($record->status),
                    'occurred_at' => $record->attendance_date?->toDateTimeString(),
                    'student_id' => $child['id'],
                    'student_name' => $child['name'],
                    'icon' => 'bi-calendar-check',
                ]);
            }
        }

        return $events
            ->filter(fn ($e) => ! empty($e['occurred_at']))
            ->sortByDesc('occurred_at')
            ->values()
            ->all();
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

    protected function gradesSummaryFromCollection($items): array
    {
        $grades = collect($items);

        return [
            'average_percent' => $grades->isEmpty()
                ? null
                : round($grades->avg(fn (StudentGrade $g) => $g->percentage()), 1),
            'count' => $grades->count(),
            'items' => $grades->take(10)->map(fn (StudentGrade $g) => [
                'id' => $g->id,
                'title' => $g->title,
                'subject' => $g->subject?->name,
                'percentage' => $g->percentage(),
                'assessed_at' => $g->assessed_at?->toDateString(),
            ])->values()->all(),
        ];
    }

    protected function walletSummaryFromModel(?\App\Models\UserWallet $wallet): array
    {
        if (! $wallet) {
            return ['balance' => 0, 'total_credited' => 0, 'total_debited' => 0, 'transactions' => []];
        }

        $credited = $wallet->transactions->where('type', 'credit')->sum('amount');
        $debited = $wallet->transactions->whereIn('type', ['debit', 'transfer_out'])->sum('amount');

        return [
            'balance' => (float) $wallet->balance,
            'total_credited' => (float) $credited,
            'total_debited' => (float) $debited,
            'transactions' => $wallet->transactions->values()->all(),
        ];
    }

    protected function behaviorSummaryFromCollection($items): array
    {
        $items = collect($items);
        $counts = ['positive' => 0, 'neutral' => 0, 'negative' => 0];

        foreach ($items as $row) {
            $counts[$row->severity] = (int) $row->total;
        }

        return ['counts' => $counts];
    }

    /**
     * @return array<string, mixed>
     */
    protected function gradesSummary(User $student): array
    {
        $grades = StudentGrade::query()
            ->where('student_id', $student->id)
            ->with('subject:id,name')
            ->orderByDesc('assessed_at')
            ->get();

        return [
            'average_percent' => $grades->isEmpty() ? null : round($grades->avg(fn ($g) => $g->percentage()), 1),
            'items' => $grades->take(5)->map(fn ($g) => [
                'id' => $g->id,
                'title' => $g->title,
                'subject' => $g->subject?->name,
                'percentage' => $g->percentage(),
                'assessed_at' => $g->assessed_at?->toDateString(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function behaviorSummary(User $student): array
    {
        $records = StudentBehaviorRecord::query()
            ->where('student_id', $student->id)
            ->orderByDesc('occurred_at')
            ->get();

        $latestNegative = $records->firstWhere('severity', 'negative');

        return [
            'positive' => $records->where('severity', 'positive')->count(),
            'neutral' => $records->where('severity', 'neutral')->count(),
            'negative' => $records->where('severity', 'negative')->count(),
            'latest_at' => $latestNegative?->occurred_at?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function walletSummary(User $student): array
    {
        $wallet = UserWallet::query()->where('user_id', $student->id)->first();

        if (! $wallet) {
            return ['balance' => 0, 'total_credited' => 0, 'total_debited' => 0, 'transactions' => []];
        }

        return [
            'balance' => (float) $wallet->balance,
            'total_credited' => (float) $wallet->total_credited,
            'total_debited' => (float) $wallet->total_debited,
            'transactions' => [],
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
            'triggered_at' => $alert->triggered_at?->toDateString(),
        ];
    }

    protected function attendanceRate(array $attendance): ?float
    {
        $total = $attendance['total'] ?? 0;
        if ($total === 0) {
            return null;
        }

        return round((($attendance['present'] ?? 0) + ($attendance['late'] ?? 0)) / $total * 100, 1);
    }

    /**
     * Navigation context for UX-7 workspace switching (no business logic).
     *
     * @param  array<string, mixed>  $profile
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    protected function workspaceContextForFamily(array $profile, array $children): array
    {
        $firstChild = $children[0] ?? null;
        $familyLabel = $firstChild && ! empty($firstChild['name'])
            ? 'Family of '.$firstChild['name']
            : 'Family of '.($profile['name'] ?? '—');

        return [
            'mode' => 'family',
            'entity_id' => $profile['id'] ?? null,
            'entity_name' => $familyLabel,
            'label' => 'Family Workspace',
            'label_ar' => 'مساحة العائلة',
            'icon' => 'bi-people-fill',
            'return_url' => route('admin.parents.index'),
            'return_label' => 'Return To Admin',
            'return_label_ar' => 'العودة للوحة الإدارة',
            'related_profile_url' => $firstChild && ! empty($firstChild['id'])
                ? route('admin.students.show', $firstChild['id'])
                : null,
            'related_profile_label' => $firstChild && ! empty($firstChild['name'])
                ? "Open Child — {$firstChild['name']}"
                : 'Open Child Profile',
            'related_profile_label_ar' => $firstChild && ! empty($firstChild['name'])
                ? "فتح الطالب — {$firstChild['name']}"
                : 'فتح ملف الطالب',
        ];
    }
}
