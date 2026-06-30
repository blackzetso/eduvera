<?php

namespace App\Support\Parent;

class FamilyCommandCenterAssembler
{
    /**
     * @param  array<string, mixed>  $profile
     * @param  array<int, array<string, mixed>>  $children
     * @param  array<int, array<string, mixed>>  $guardians
     * @param  array<int, array<string, mixed>>  $admissionFollowUps
     * @param  array<int, array<string, mixed>>  $timelineEvents
     * @return array<string, mixed>
     */
    public function assemble(
        array $profile,
        array $children,
        array $guardians,
        array $admissionFollowUps,
        array $timelineEvents,
    ): array {
        $finance = $this->financeSnapshot($profile, $children);
        $attendance = $this->attendanceSnapshot($children);
        $academic = $this->academicSnapshot($children);
        $risks = $this->riskPanel($children, $admissionFollowUps);
        $health = $this->healthDashboard($profile, $children, $attendance, $finance, $risks);

        return [
            'family_context' => [
                'parentId' => $profile['id'],
                'parentCode' => $profile['parent_code'],
                'childrenCount' => count($children),
                'guardianCount' => count($guardians),
                'familyWalletBalance' => $profile['family_wallet_balance'] ?? 0,
            ],
            'health' => $health,
            'children' => $children,
            'finance_snapshot' => $finance,
            'attendance_snapshot' => $attendance,
            'academic_snapshot' => $academic,
            'risks' => $risks,
            'timeline_preview' => $this->timelinePreview($timelineEvents),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    protected function financeSnapshot(array $profile, array $children): array
    {
        $outstanding = 0.0;
        $paid = 0.0;
        $walletBalance = (float) ($profile['wallet_balance'] ?? 0);

        foreach ($children as $child) {
            $outstanding += (float) ($child['finance']['outstanding_balance'] ?? 0);
            $paid += (float) ($child['finance']['paid_this_year'] ?? 0);
            $walletBalance += (float) ($child['finance']['wallet_balance'] ?? 0);
        }

        $overdue = collect($children)->where('finance.installment_status', 'overdue')->count();
        $dueSoon = collect($children)->where('finance.installment_status', 'due_soon')->count();

        $level = match (true) {
            $outstanding > 0 || $overdue > 0 => 'red',
            $dueSoon > 0 => 'amber',
            default => 'green',
        };

        return [
            'outstanding_balance' => round($outstanding, 2),
            'paid_this_year' => round($paid, 2),
            'upcoming_installments' => $dueSoon,
            'overdue_installments' => $overdue,
            'wallet_balance' => round($walletBalance, 2),
            'status' => $level,
            'status_label' => $this->levelLabel($level),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    protected function attendanceSnapshot(array $children): array
    {
        $rates = collect($children)
            ->pluck('attendance.rate_percent')
            ->filter(fn ($r) => $r !== null);

        $familyAverage = $rates->isEmpty() ? null : round($rates->avg(), 1);

        $students = collect($children)->map(fn (array $child) => [
            'student_id' => $child['id'],
            'student_name' => $child['name'],
            'rate_percent' => $child['attendance']['rate_percent'] ?? null,
            'absent' => $child['attendance']['absent'] ?? 0,
            'late' => $child['attendance']['late'] ?? 0,
            'below_threshold' => ($child['attendance']['rate_percent'] ?? 100) < 70,
            'has_alert' => (bool) ($child['attendance']['active_alert'] ?? false),
        ])->values()->all();

        $level = match (true) {
            collect($students)->contains(fn ($s) => $s['has_alert'] || $s['below_threshold']) => 'red',
            $familyAverage !== null && $familyAverage < 85 => 'amber',
            default => 'green',
        };

        return [
            'family_average' => $familyAverage,
            'status' => $level,
            'status_label' => $this->levelLabel($level),
            'students' => $students,
            'below_threshold_count' => collect($students)->where('below_threshold', true)->count(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    protected function academicSnapshot(array $children): array
    {
        $ranked = collect($children)
            ->filter(fn ($c) => ($c['academic']['average_percent'] ?? null) !== null)
            ->sortByDesc('academic.average_percent')
            ->values();

        $highest = $ranked->first();
        $lowest = $ranked->sortBy('academic.average_percent')->first();

        return [
            'students' => collect($children)->map(fn (array $child) => [
                'student_id' => $child['id'],
                'student_name' => $child['name'],
                'grade_label' => $child['grade_label'] ?? null,
                'average_percent' => $child['academic']['average_percent'] ?? null,
                'recent_assessments' => $child['academic']['recent'] ?? [],
            ])->values()->all(),
            'highest_performer' => $highest ? [
                'student_id' => $highest['id'],
                'student_name' => $highest['name'],
                'average_percent' => $highest['academic']['average_percent'],
            ] : null,
            'needs_support' => $lowest && ($lowest['academic']['average_percent'] ?? 100) < 60 ? [
                'student_id' => $lowest['id'],
                'student_name' => $lowest['name'],
                'average_percent' => $lowest['academic']['average_percent'],
            ] : null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @param  array<int, array<string, mixed>>  $admissionFollowUps
     * @return array<int, array<string, mixed>>
     */
    protected function riskPanel(array $children, array $admissionFollowUps): array
    {
        $risks = [];

        foreach ($children as $child) {
            if ($child['attendance']['active_alert'] ?? false) {
                $risks[] = [
                    'id' => 'attendance-' . $child['id'],
                    'severity' => 'critical',
                    'severity_label' => 'حرج',
                    'icon' => 'bi-exclamation-triangle-fill',
                    'title' => 'الحضور دون العتبة',
                    'message' => "تنبيه حضور لـ {$child['name']}",
                    'date' => $child['attendance']['alert_triggered_at'] ?? null,
                    'source' => 'attendance',
                    'source_label' => 'الحضور',
                    'student_id' => $child['id'],
                    'student_name' => $child['name'],
                ];
            } elseif (($child['attendance']['rate_percent'] ?? null) !== null && $child['attendance']['rate_percent'] < 70) {
                $risks[] = [
                    'id' => 'attendance-low-' . $child['id'],
                    'severity' => 'warning',
                    'severity_label' => 'تحذير',
                    'icon' => 'bi-calendar-x',
                    'title' => 'نسبة حضور منخفضة',
                    'message' => "{$child['name']}: {$child['attendance']['rate_percent']}%",
                    'date' => null,
                    'source' => 'attendance',
                    'source_label' => 'الحضور',
                    'student_id' => $child['id'],
                    'student_name' => $child['name'],
                ];
            }

            if (($child['finance']['wallet_balance'] ?? 0) < 0) {
                $risks[] = [
                    'id' => 'finance-' . $child['id'],
                    'severity' => 'critical',
                    'severity_label' => 'حرج',
                    'icon' => 'bi-cash-stack',
                    'title' => 'رصيد مستحق',
                    'message' => "رصيد سالب لـ {$child['name']}",
                    'date' => null,
                    'source' => 'finance',
                    'source_label' => 'المالية',
                    'student_id' => $child['id'],
                    'student_name' => $child['name'],
                ];
            }

            if (($child['behavior']['negative'] ?? 0) > 0) {
                $risks[] = [
                    'id' => 'behavior-' . $child['id'],
                    'severity' => ($child['behavior']['negative'] ?? 0) >= 3 ? 'critical' : 'warning',
                    'severity_label' => ($child['behavior']['negative'] ?? 0) >= 3 ? 'حرج' : 'تحذير',
                    'icon' => 'bi-emoji-frown',
                    'title' => 'حوادث سلوكية',
                    'message' => "{$child['behavior']['negative']} ملاحظة سلبية — {$child['name']}",
                    'date' => $child['behavior']['latest_at'] ?? null,
                    'source' => 'behavior',
                    'source_label' => 'السلوك',
                    'student_id' => $child['id'],
                    'student_name' => $child['name'],
                ];
            }
        }

        foreach ($admissionFollowUps as $admission) {
            $risks[] = [
                'id' => 'admission-' . $admission['id'],
                'severity' => 'info',
                'severity_label' => 'معلومة',
                'icon' => 'bi-file-earmark-person',
                'title' => 'متابعة قبول',
                'message' => $admission['message'],
                'date' => $admission['date'] ?? null,
                'source' => 'admission',
                'source_label' => 'القبول',
                'student_id' => null,
                'student_name' => $admission['applicant_name'] ?? null,
            ];
        }

        usort($risks, fn ($a, $b) => $this->severityWeight($b['severity']) <=> $this->severityWeight($a['severity']));

        return $risks;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    protected function healthDashboard(
        array $profile,
        array $children,
        array $attendance,
        array $finance,
        array $risks,
    ): array {
        $activeStudents = collect($children)->where('status', 'active')->count();

        return [
            'active_students' => $this->healthCard('active_students', 'طلاب نشطون', 'green', (string) $activeStudents, (string) count($children), 0),
            'attendance_average' => $this->healthCard(
                'attendance_average',
                'متوسط الحضور',
                $attendance['status'] ?? 'amber',
                $attendance['family_average'] !== null ? "{$attendance['family_average']}%" : '—',
                $attendance['status_label'] ?? '—',
                $attendance['below_threshold_count'] ?? 0,
            ),
            'outstanding_balance' => $this->healthCard(
                'outstanding_balance',
                'رصيد مستحق',
                $finance['status'] ?? 'green',
                number_format($finance['outstanding_balance'] ?? 0, 2),
                $finance['status_label'] ?? '—',
                ($finance['overdue_installments'] ?? 0) > 0 ? 1 : 0,
            ),
            'open_risks' => $this->healthCard(
                'open_risks',
                'مخاطر مفتوحة',
                count($risks) >= 3 ? 'red' : (count($risks) > 0 ? 'amber' : 'green'),
                (string) count($risks),
                count($risks) > 0 ? 'يتطلب متابعة' : 'لا مخاطر',
                count($risks),
            ),
            'wallet_balance' => $this->healthCard(
                'wallet_balance',
                'رصيد المحفظة',
                ($finance['wallet_balance'] ?? 0) < 0 ? 'red' : 'green',
                number_format($finance['wallet_balance'] ?? 0, 2),
                'إجمالي العائلة',
                0,
            ),
            'pending_tasks' => $this->healthCard(
                'pending_tasks',
                'مهام معلقة',
                count($profile['pending_admissions'] ?? []) > 0 ? 'amber' : 'green',
                (string) count($profile['pending_admissions'] ?? []),
                'متابعات القبول',
                count($profile['pending_admissions'] ?? []),
            ),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array<int, array<string, mixed>>
     */
    protected function timelinePreview(array $events): array
    {
        return collect($events)
            ->filter(fn ($e) => ! empty($e['occurred_at']))
            ->sortByDesc('occurred_at')
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function healthCard(
        string $id,
        string $label,
        string $level,
        string $value,
        string $summary,
        int $alertCount,
    ): array {
        return [
            'id' => $id,
            'label' => $label,
            'status' => $level,
            'status_label' => $this->levelLabel($level),
            'value' => $value,
            'summary' => $summary,
            'alert_count' => $alertCount,
        ];
    }

    protected function levelLabel(string $level): string
    {
        return match ($level) {
            'green' => 'جيد',
            'amber' => 'انتباه',
            'red' => 'خطر',
            default => '—',
        };
    }

    protected function severityWeight(string $severity): int
    {
        return match ($severity) {
            'critical' => 3,
            'warning' => 2,
            default => 1,
        };
    }
}
