<?php

namespace App\Support\Admission;

use Carbon\Carbon;

/**
 * Single source of truth for admissions inbox attention and priority rules.
 */
class AdmissionInboxAttention
{
    public static function isVisitToday(?string $visitDate): bool
    {
        return $visitDate !== null && $visitDate === now()->toDateString();
    }

    public static function isReadyForConversion(array $row): bool
    {
        return ($row['decision'] ?? null) === AdmissionDecision::ACCEPTED
            && ($row['pipeline_stage'] ?? null) === AdmissionStage::APPLICATION
            && ($row['status'] ?? null) === AdmissionStatus::OPEN;
    }

    public static function isStale(array $row, int $days = 7): bool
    {
        if (($row['status'] ?? null) !== AdmissionStatus::OPEN) {
            return false;
        }

        $createdAt = $row['created_at'] ?? null;
        if (! $createdAt) {
            return false;
        }

        return Carbon::parse($createdAt)->lt(now()->subDays($days));
    }

    public static function isMissingTargetGrade(array $row): bool
    {
        return ($row['status'] ?? null) === AdmissionStatus::OPEN
            && ($row['pipeline_stage'] ?? null) === AdmissionStage::APPLICATION
            && empty($row['target_grade']);
    }

    public static function needsFollowUp(array $row): bool
    {
        return self::isReadyForConversion($row)
            || self::isVisitToday($row['visit_date'] ?? null)
            || empty($row['assigned_to'])
            || self::isMissingTargetGrade($row)
            || self::isStale($row);
    }

    /**
     * @return array{score: int, tags: array<int, array{type: string, label: string, level: string}>, level: string}
     */
    public static function priorityMeta(array $row): array
    {
        $tags = [];
        $score = 0;

        if (self::isReadyForConversion($row)) {
            $score += 100;
            $tags[] = ['type' => 'convert', 'label' => 'جاهز للتحويل', 'level' => 'high'];
        }

        if (self::isVisitToday($row['visit_date'] ?? null)) {
            $score += 80;
            $tags[] = ['type' => 'visit', 'label' => 'زيارة اليوم', 'level' => 'high'];
        }

        if (empty($row['assigned_to'])) {
            $score += 60;
            $tags[] = ['type' => 'assign', 'label' => 'بدون مسؤول', 'level' => 'medium'];
        }

        if (self::isMissingTargetGrade($row)) {
            $score += 50;
            $tags[] = ['type' => 'incomplete', 'label' => 'بيانات ناقصة', 'level' => 'medium'];
        }

        if (self::isStale($row)) {
            $score += 40;
            $tags[] = ['type' => 'stale', 'label' => 'بدون متابعة حديثة', 'level' => 'low'];
        }

        if (
            ($row['decision'] ?? null) === AdmissionDecision::ACCEPTED
            && ($row['status'] ?? null) === AdmissionStatus::OPEN
            && ! self::isReadyForConversion($row)
        ) {
            $score += 45;
            $tags[] = ['type' => 'accepted', 'label' => 'مقبول — يحتاج إكمال', 'level' => 'medium'];
        }

        $level = $score >= 80 ? 'high' : ($score >= 50 ? 'medium' : 'low');

        return ['score' => $score, 'tags' => $tags, 'level' => $level];
    }
}
