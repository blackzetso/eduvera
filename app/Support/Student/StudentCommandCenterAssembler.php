<?php

namespace App\Support\Student;

use App\Models\User;

class StudentCommandCenterAssembler
{
    /**
     * @param  array<string, mixed>  $guardians
     * @param  array<int, array<string, mixed>>  $siblings
     * @param  array<string, mixed>  $overview
     * @param  array<string, mixed>  $attendance
     * @param  array<string, mixed>  $grades
     * @param  array<string, mixed>  $behavior
     * @param  array<string, mixed>  $wallet
     * @param  array<string, mixed>  $enrollments
     * @param  array<string, mixed>|null  $classInfo
     * @param  array<int, array<string, mixed>>  $activityTimeline
     * @return array<string, mixed>
     */
    public function assemble(
        User $student,
        array $guardians,
        array $siblings,
        array $overview,
        array $attendance,
        array $grades,
        array $behavior,
        array $wallet,
        array $enrollments,
        ?array $classInfo,
        array $activityTimeline,
    ): array {
        $health = $this->healthDashboard($overview, $attendance, $grades, $behavior, $wallet, $enrollments);
        $risks = $this->riskAlerts($overview, $attendance, $behavior, $wallet, $enrollments);

        return [
            'student_context' => [
                'studentId' => $student->id,
                'studentCode' => $student->student_code,
                'category' => $classInfo['path_label'] ?? $student->category?->name,
                'guardianCount' => count($guardians),
                'siblingCount' => count($siblings),
            ],
            'health' => $health,
            'risks' => $risks,
            'finance_snapshot' => $this->financeSnapshot($wallet),
            'timeline_preview' => $this->timelinePreview(
                $activityTimeline,
                $attendance,
                $behavior,
                $wallet,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function healthDashboard(
        array $overview,
        array $attendance,
        array $grades,
        array $behavior,
        array $wallet,
        array $enrollments,
    ): array {
        $rate = $overview['attendance_rate_percent'] ?? $attendance['summary']['rate_percent'] ?? null;
        $attendanceLevel = $overview['active_alert']
            ? 'red'
            : $this->levelFromThreshold($rate, 85, 70, true);

        $avgGrade = $grades['average_percent'] ?? $overview['grades_average'] ?? null;
        $academicLevel = $this->levelFromThreshold($avgGrade, 75, 60, true);

        $negativeBehavior = $behavior['counts']['negative'] ?? $overview['behavior']['negative'] ?? 0;
        $behaviorLevel = match (true) {
            $negativeBehavior >= 3 => 'red',
            $negativeBehavior >= 1 => 'amber',
            default => 'green',
        };

        $balance = (float) ($wallet['balance'] ?? $overview['wallet_balance'] ?? 0);
        $financialLevel = match (true) {
            $balance < 0 => 'red',
            $balance < 50 => 'amber',
            default => 'green',
        };

        $walletLevel = $financialLevel;

        $current = $enrollments['current'] ?? null;
        $enrollmentLevel = match (true) {
            ! $current => 'red',
            ($current['status'] ?? '') === 'withdrawn' => 'red',
            ($current['status'] ?? '') === 'active' => 'green',
            default => 'amber',
        };

        return [
            'attendance' => $this->healthCard(
                'attendance',
                'صحة الحضور',
                $attendanceLevel,
                $rate !== null ? "{$rate}%" : '—',
                $rate !== null ? 'stable' : 'unknown',
                $overview['active_alert'] ? 1 : 0,
                $this->attendanceHealthSummary($rate, $overview['active_alert'] ?? null),
            ),
            'academic' => $this->healthCard(
                'academic',
                'الصحة الأكاديمية',
                $academicLevel,
                $avgGrade !== null ? "{$avgGrade}%" : '—',
                $avgGrade !== null ? 'stable' : 'unknown',
                0,
                $avgGrade !== null
                    ? "متوسط الدرجات {$avgGrade}%"
                    : 'لا توجد درجات كافية للتقييم',
            ),
            'behavior' => $this->healthCard(
                'behavior',
                'صحة السلوك',
                $behaviorLevel,
                (string) $negativeBehavior,
                $negativeBehavior > 0 ? 'down' : 'stable',
                $negativeBehavior,
                $negativeBehavior > 0
                    ? "{$negativeBehavior} ملاحظة سلبية"
                    : 'لا توجد ملاحظات سلبية',
            ),
            'financial' => $this->healthCard(
                'financial',
                'الصحة المالية',
                $financialLevel,
                number_format($balance, 2),
                $balance < 0 ? 'down' : 'stable',
                $balance < 0 ? 1 : 0,
                $balance < 0 ? 'رصيد سالب' : 'الوضع المالي مستقر',
            ),
            'wallet' => $this->healthCard(
                'wallet',
                'حالة المحفظة',
                $walletLevel,
                number_format($balance, 2),
                'stable',
                0,
                'رصيد المحفظة الحالي',
            ),
            'enrollment' => $this->healthCard(
                'enrollment',
                'صحة القيد',
                $enrollmentLevel,
                $current['status_label'] ?? '—',
                ! $current ? 'down' : 'stable',
                ! $current ? 1 : 0,
                $current
                    ? ($current['path_label'] ?? 'قيد نشط')
                    : 'لا يوجد قيد حالي',
            ),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function riskAlerts(
        array $overview,
        array $attendance,
        array $behavior,
        array $wallet,
        array $enrollments,
    ): array {
        $risks = [];

        if ($alert = $overview['active_alert'] ?? null) {
            $risks[] = [
                'id' => 'attendance-threshold',
                'severity' => 'high',
                'severity_label' => 'عالي',
                'icon' => 'bi-exclamation-triangle-fill',
                'title' => 'الحضور دون العتبة',
                'message' => "تنبيه حضور ({$alert['level']}) — غيابات: {$alert['absences_count']}",
                'date' => $alert['triggered_at'] ?? null,
                'source' => 'attendance',
                'source_label' => 'الحضور',
            ];
        }

        $rate = $overview['attendance_rate_percent'] ?? $attendance['summary']['rate_percent'] ?? null;
        if ($rate !== null && $rate < 70 && ! ($overview['active_alert'] ?? null)) {
            $risks[] = [
                'id' => 'attendance-low-rate',
                'severity' => 'medium',
                'severity_label' => 'متوسط',
                'icon' => 'bi-calendar-x',
                'title' => 'نسبة حضور منخفضة',
                'message' => "نسبة الحضور {$rate}% أقل من 70%",
                'date' => $overview['last_attendance_date'] ?? null,
                'source' => 'attendance',
                'source_label' => 'الحضور',
            ];
        }

        $balance = (float) ($wallet['balance'] ?? 0);
        if ($balance < 0) {
            $risks[] = [
                'id' => 'outstanding-balance',
                'severity' => 'high',
                'severity_label' => 'عالي',
                'icon' => 'bi-cash-stack',
                'title' => 'رصيد مستحق',
                'message' => 'رصيد المحفظة سالب — يوجد مبلغ مستحق',
                'date' => collect($wallet['transactions'] ?? [])->first()['created_at'] ?? null,
                'source' => 'finance',
                'source_label' => 'المالية',
            ];
        }

        $negativeCount = $behavior['counts']['negative'] ?? 0;
        if ($negativeCount > 0) {
            $latest = collect($behavior['items'] ?? [])->firstWhere('severity', 'negative');
            $risks[] = [
                'id' => 'behavior-incidents',
                'severity' => $negativeCount >= 3 ? 'high' : 'medium',
                'severity_label' => $negativeCount >= 3 ? 'عالي' : 'متوسط',
                'icon' => 'bi-emoji-frown',
                'title' => 'حوادث سلوكية',
                'message' => "{$negativeCount} ملاحظة سلوكية سلبية مسجلة",
                'date' => $latest['occurred_at'] ?? null,
                'source' => 'behavior',
                'source_label' => 'السلوك',
            ];
        }

        if (! ($enrollments['current'] ?? null)) {
            $risks[] = [
                'id' => 'enrollment-issue',
                'severity' => 'high',
                'severity_label' => 'عالي',
                'icon' => 'bi-journal-x',
                'title' => 'مشكلة في القيد',
                'message' => 'لا يوجد قيد دراسي حالي للطالب',
                'date' => null,
                'source' => 'enrollment',
                'source_label' => 'القيد',
            ];
        } elseif (($enrollments['current']['status'] ?? '') === 'withdrawn') {
            $risks[] = [
                'id' => 'enrollment-withdrawn',
                'severity' => 'medium',
                'severity_label' => 'متوسط',
                'icon' => 'bi-box-arrow-left',
                'title' => 'قيد منسحب',
                'message' => 'القيد الحالي بحالة منسحب',
                'date' => $enrollments['current']['withdrawal_date'] ?? null,
                'source' => 'enrollment',
                'source_label' => 'القيد',
            ];
        }

        usort($risks, fn ($a, $b) => $this->severityWeight($b['severity']) <=> $this->severityWeight($a['severity']));

        return $risks;
    }

    /**
     * @return array<string, mixed>
     */
    protected function financeSnapshot(array $wallet): array
    {
        $balance = (float) ($wallet['balance'] ?? 0);
        $credited = (float) ($wallet['total_credited'] ?? 0);
        $debited = (float) ($wallet['total_debited'] ?? 0);
        $outstanding = max(0, $debited - $credited);

        return [
            'outstanding_balance' => $outstanding,
            'paid_this_year' => $credited,
            'next_due_date' => null,
            'installment_status' => $balance < 0 ? 'overdue' : ($balance < 50 ? 'due_soon' : 'current'),
            'installment_status_label' => match (true) {
                $balance < 0 => 'متأخر',
                $balance < 50 => 'قريب الاستحقاق',
                default => 'منتظم',
            },
            'wallet_balance' => $balance,
            'recent_transactions' => collect($wallet['transactions'] ?? [])->take(5)->values()->all(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $activityTimeline
     * @return array<int, array<string, mixed>>
     */
    protected function timelinePreview(
        array $activityTimeline,
        array $attendance,
        array $behavior,
        array $wallet,
    ): array {
        $events = collect($activityTimeline);

        foreach (collect($behavior['items'] ?? [])->take(3) as $item) {
            $events->push([
                'id' => 'behavior-' . $item['id'],
                'type' => 'behavior',
                'title' => $item['title'],
                'subtitle' => $item['category'] ?? 'ملاحظة سلوكية',
                'occurred_at' => $item['occurred_at'],
                'badge_class' => $item['severity'] === 'negative' ? 'bg-danger' : 'bg-success',
                'icon' => 'bi-emoji-smile',
            ]);
        }

        foreach (collect($attendance['records'] ?? [])->take(3) as $record) {
            $events->push([
                'id' => 'attendance-' . $record['id'],
                'type' => 'attendance',
                'title' => 'سجل حضور — ' . ($record['status_label'] ?? $record['status']),
                'subtitle' => $record['session_label'] ?? $record['session_type'] ?? null,
                'occurred_at' => $record['attendance_date'],
                'badge_class' => ($record['status'] ?? '') === 'absent' ? 'bg-danger' : 'bg-success',
                'icon' => 'bi-calendar-check',
            ]);
        }

        foreach (collect($wallet['transactions'] ?? [])->take(3) as $tx) {
            $events->push([
                'id' => 'wallet-' . $tx['id'],
                'type' => 'wallet',
                'title' => 'حركة محفظة',
                'subtitle' => $tx['description'] ?? $tx['type'],
                'occurred_at' => $tx['created_at'],
                'badge_class' => 'bg-warning text-dark',
                'icon' => 'bi-wallet2',
            ]);
        }

        return $events
            ->filter(fn ($e) => ! empty($e['occurred_at']))
            ->sortByDesc('occurred_at')
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $alert
     */
    protected function attendanceHealthSummary(?float $rate, ?array $alert): string
    {
        if ($alert) {
            return 'تنبيه حضور نشط';
        }

        if ($rate === null) {
            return 'لا توجد بيانات حضور كافية';
        }

        return "نسبة الحضور {$rate}%";
    }

    /**
     * @return array<string, mixed>
     */
    protected function healthCard(
        string $id,
        string $label,
        string $level,
        string $value,
        string $trend,
        int $alertCount,
        string $summary,
    ): array {
        return [
            'id' => $id,
            'label' => $label,
            'status' => $level,
            'status_label' => $this->levelLabel($level),
            'value' => $value,
            'trend' => $trend,
            'trend_label' => match ($trend) {
                'up' => 'تحسّن',
                'down' => 'تراجع',
                'stable' => 'مستقر',
                default => 'غير معروف',
            },
            'summary' => $summary,
            'alert_count' => $alertCount,
        ];
    }

    protected function levelFromThreshold(?float $value, float $greenMin, float $amberMin, bool $higherIsBetter): string
    {
        if ($value === null) {
            return 'amber';
        }

        if ($higherIsBetter) {
            return match (true) {
                $value >= $greenMin => 'green',
                $value >= $amberMin => 'amber',
                default => 'red',
            };
        }

        return match (true) {
            $value <= $greenMin => 'green',
            $value <= $amberMin => 'amber',
            default => 'red',
        };
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
            'high' => 3,
            'medium' => 2,
            default => 1,
        };
    }
}
