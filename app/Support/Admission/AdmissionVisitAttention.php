<?php

namespace App\Support\Admission;

use Carbon\Carbon;

/**
 * Single source of truth for visit command-center attention rules.
 */
class AdmissionVisitAttention
{
    public const POSITIVE_OUTCOMES = ['positive', 'interested', 'highly_interested', 'requested_application'];

    public const INTERESTED_OUTCOMES = ['positive', 'interested', 'highly_interested', 'requested_application', 'waitlist_candidate'];

    public static function colorKey(array $visit): string
    {
        if (($visit['status'] ?? '') === 'no_show' || ($visit['attendance_status'] ?? '') === 'no_show') {
            return 'no_show';
        }

        if (($visit['outcome'] ?? '') === 'rescheduled' || ($visit['status'] ?? '') === 'cancelled') {
            return 'rescheduled';
        }

        if (($visit['attendance_status'] ?? '') === 'attended' || ($visit['status'] ?? '') === 'completed') {
            return 'attended';
        }

        return 'requested';
    }

    public static function daysSince(?string $dateStr): ?int
    {
        if (! $dateStr) {
            return null;
        }

        $visitDay = Carbon::parse($dateStr)->startOfDay();
        $today = now()->startOfDay();

        return $visitDay->lte($today)
            ? (int) $visitDay->diffInDays($today)
            : 0;
    }

    public static function needsFollowUp(array $visit): bool
    {
        if (! in_array($visit['outcome'] ?? '', self::POSITIVE_OUTCOMES, true)) {
            return false;
        }

        if (($visit['pipeline_stage'] ?? '') !== AdmissionStage::CAMPUS_VISIT) {
            return false;
        }

        if (($visit['application_status'] ?? '') !== AdmissionStatus::OPEN) {
            return false;
        }

        $visitDays = self::daysSince($visit['scheduled_date'] ?? null);

        return $visitDays !== null && $visitDays >= 3;
    }

    /**
     * @return array<int, array{type: string, label: string, class: string}>
     */
    public static function alerts(array $visit, ?string $today = null): array
    {
        $today = $today ?? now()->toDateString();
        $alerts = [];
        $visitDays = self::daysSince($visit['scheduled_date'] ?? null);

        if (
            ($visit['scheduled_date'] ?? '') === $today
            && empty($visit['attendance_status'])
            && ! in_array($visit['status'] ?? '', ['completed', 'no_show', 'cancelled'], true)
        ) {
            $alerts[] = ['type' => 'action', 'label' => 'إجراء مطلوب', 'class' => 'bg-warning text-dark'];
        }

        if (
            $visitDays !== null
            && $visitDays >= 7
            && ($visit['pipeline_stage'] ?? '') === AdmissionStage::CAMPUS_VISIT
            && in_array($visit['outcome'] ?? '', self::POSITIVE_OUTCOMES, true)
        ) {
            $alerts[] = ['type' => 'followup', 'label' => 'يحتاج متابعة', 'class' => 'bg-danger'];
        }

        if (
            $visitDays !== null
            && $visitDays >= 3
            && $visitDays < 7
            && ($visit['pipeline_stage'] ?? '') === AdmissionStage::CAMPUS_VISIT
            && in_array($visit['outcome'] ?? '', self::POSITIVE_OUTCOMES, true)
        ) {
            $alerts[] = ['type' => 'followup', 'label' => 'متابعة', 'class' => 'bg-info text-dark'];
        }

        return $alerts;
    }

    /**
     * @return array{level: string, label: string, bar: string}
     */
    public static function followUpPriority(array $visit): array
    {
        $visitDays = self::daysSince($visit['scheduled_date'] ?? null) ?? 0;

        if ($visitDays >= 7) {
            return ['level' => 'high', 'label' => 'عالي', 'bar' => 'bg-danger'];
        }

        if ($visitDays >= 5) {
            return ['level' => 'medium', 'label' => 'متوسط', 'bar' => 'bg-warning'];
        }

        return ['level' => 'low', 'label' => 'منخفض', 'bar' => 'bg-info'];
    }

    public static function todayBoardColumn(array $visit, string $today): ?string
    {
        if (($visit['scheduled_date'] ?? '') !== $today) {
            return null;
        }

        if (($visit['status'] ?? '') === 'no_show' || ($visit['attendance_status'] ?? '') === 'no_show') {
            return 'no_show';
        }

        if (($visit['status'] ?? '') === 'completed') {
            return 'completed';
        }

        if (($visit['attendance_status'] ?? '') === 'attended') {
            return 'checked_in';
        }

        if (in_array($visit['status'] ?? '', ['requested', 'confirmed'], true)) {
            return 'scheduled';
        }

        return 'scheduled';
    }

    /**
     * @return array<string, mixed>
     */
    public static function enrichRow(array $visit, ?string $today = null): array
    {
        $today = $today ?? now()->toDateString();

        return array_merge($visit, [
            'color_key' => self::colorKey($visit),
            'needs_follow_up' => self::needsFollowUp($visit),
            'alerts' => self::alerts($visit, $today),
            'follow_up_priority' => self::followUpPriority($visit),
            'is_interested' => in_array($visit['outcome'] ?? '', self::INTERESTED_OUTCOMES, true),
            'days_since_visit' => self::daysSince($visit['scheduled_date'] ?? null),
        ]);
    }
}
