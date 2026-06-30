<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\TimetableAssignment;
use App\Models\TimetablePeriod;
use App\Models\User;

class CoveragePrioritySettingsService
{
    public const SETTING_KEY = 'daily_coverage_priority_rules';

    public function getRules(): array
    {
        $defaults = config('attendance.coverage_priority_defaults', []);
        $stored = Setting::query()->where('key', self::SETTING_KEY)->value('value');

        if (!$stored) {
            return $defaults;
        }

        $decoded = json_decode($stored, true);
        if (!is_array($decoded)) {
            return $defaults;
        }

        return $this->mergeRules($defaults, $decoded);
    }

    public function saveRules(array $payload): void
    {
        $merged = $this->mergeRules(config('attendance.coverage_priority_defaults', []), $payload);
        Setting::updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => json_encode($merged, JSON_UNESCAPED_UNICODE)]
        );
    }

    /**
     * @return array{enabled_rules: array, rules: array, balance_penalty_per_point: int, week_penalty_per_coverage: int}
     */
    public function maxPossibleScore(): int
    {
        $rules = $this->getRules()['rules'] ?? [];
        $sum = 0;
        foreach ($rules as $rule) {
            if (!empty($rule['enabled'])) {
                $sum += (int) ($rule['weight'] ?? 0);
            }
        }

        return max(1, $sum);
    }

    public function forPreview(): array
    {
        $rules = $this->getRules();

        return [
            'rules' => $rules['rules'] ?? [],
            'balance_penalty_per_point' => (int) ($rules['balance_penalty_per_point'] ?? 4),
            'week_penalty_per_coverage' => (int) ($rules['week_penalty_per_coverage'] ?? 5),
            'enabled_rules' => collect($rules['rules'] ?? [])
                ->filter(fn ($r) => !empty($r['enabled']))
                ->sortByDesc('weight')
                ->map(fn ($r, $key) => [
                    'key' => $key,
                    'label' => $r['label'] ?? $key,
                    'weight' => (int) ($r['weight'] ?? 0),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $absentTeacher
     */
    public function scoreSubstitute(
        User $teacher,
        TimetablePeriod $period,
        TimetableAssignment $main,
        array $stageIds,
        array $coverageBalances,
        ?array $absentTeacher = null
    ): array {
        $config = $this->getRules();
        $ruleSet = $config['rules'] ?? [];
        $score = 0;
        $reasons = [];
        $warnings = [];
        $matchedKeys = [];

        $apply = function (string $key, bool $matched) use (&$score, &$reasons, &$matchedKeys, $ruleSet) {
            $rule = $ruleSet[$key] ?? null;
            if (!$rule || empty($rule['enabled']) || !$matched) {
                return;
            }
            $weight = (int) ($rule['weight'] ?? 0);
            $score += $weight;
            $reasons[] = $rule['label'] ?? $key;
            $matchedKeys[] = $key;
        };

        $subjectIds = $teacher->subjects->pluck('id')->all();
        $apply('same_subject', in_array($main->subject_id, $subjectIds, true));
        if (!in_array('same_subject', $matchedKeys, true) && ($ruleSet['same_subject']['enabled'] ?? false)) {
            $warnings[] = 'مادة مختلفة';
        }

        $teacherDept = $this->normalizeBlob($teacher->department ?? '');
        $absentDept = $this->normalizeBlob($absentTeacher['department'] ?? '');
        $apply(
            'same_department',
            $teacherDept !== '' && $absentDept !== '' && $this->textsOverlap($teacherDept, $absentDept)
        );

        $blob = $this->normalizeBlob(($teacher->department ?? '').' '.($teacher->job_title ?? ''));
        $stageMatched = false;
        foreach ($stageIds as $sid) {
            if ($sid && str_contains($blob, $this->normalizeBlob(substr((string) $sid, 0, 6)))) {
                $stageMatched = true;
                break;
            }
        }
        $apply('same_stage', $stageMatched);
        if (!$stageMatched && ($ruleSet['same_stage']['enabled'] ?? false)) {
            $warnings[] = 'مرحلة مختلفة';
        }

        $categoryName = $this->normalizeBlob($period->category?->name ?? '');
        $apply(
            'same_grade',
            $categoryName !== '' && $categoryName !== '' && str_contains($blob, $categoryName)
        );

        $apply('free_period', true);

        $balance = (int) ($coverageBalances[$teacher->id]['total'] ?? 0);
        $penaltyPerPoint = (int) ($config['balance_penalty_per_point'] ?? 4);
        if (!empty($ruleSet['low_coverage_balance']['enabled'])) {
            $bonus = max(0, (int) ($ruleSet['low_coverage_balance']['weight'] ?? 40) - ($balance * $penaltyPerPoint));
            $score += $bonus;
            if ($balance <= 2) {
                $reasons[] = $ruleSet['low_coverage_balance']['label'] ?? 'رصيد تغطية أقل';
                $matchedKeys[] = 'low_coverage_balance';
            }
        }

        $weekExtra = (int) ($coverageBalances[$teacher->id]['week'] ?? 0);
        $weekPenalty = (int) ($config['week_penalty_per_coverage'] ?? 5);
        $score += max(0, 30 - ($weekExtra * $weekPenalty));

        $apply(
            'department_head',
            str_contains($blob, 'رئيس') || str_contains($blob, 'قسم')
        );

        return [
            'score' => $score,
            'reject' => false,
            'reasons' => array_values(array_unique($reasons)),
            'warnings' => $warnings,
            'matched_priority_keys' => $matchedKeys,
            'recommendation_explanation' => $this->recommendationExplanation($matchedKeys, $warnings),
        ];
    }

    /**
     * Structured ✓/✗ lines derived from the same priority rules used in scoring.
     *
     * @return array<int, array{ok: bool, text: string}>
     */
    public function recommendationExplanation(array $matchedKeys, array $warnings = []): array
    {
        $rules = $this->getRules()['rules'] ?? [];
        $checks = [
            'same_subject' => ['ok' => 'نفس المادة', 'fail' => 'ليس نفس المادة'],
            'same_stage' => ['ok' => 'نفس المرحلة', 'fail' => 'ليس نفس المرحلة'],
            'same_department' => ['ok' => 'نفس القسم', 'fail' => 'ليس نفس القسم'],
            'same_grade' => ['ok' => 'نفس الصف / الفصل', 'fail' => 'ليس نفس الصف'],
            'free_period' => ['ok' => 'متاح في هذا التوقيت', 'fail' => 'غير متاح في هذا التوقيت'],
            'low_coverage_balance' => ['ok' => 'رصيد تغطية منخفض', 'fail' => 'رصيد تغطية مرتفع'],
            'department_head' => ['ok' => 'رئيس قسم / خبرة', 'fail' => null],
        ];

        $lines = [];
        foreach ($checks as $key => $labels) {
            $rule = $rules[$key] ?? null;
            if (!$rule || empty($rule['enabled'])) {
                continue;
            }
            $isMatched = in_array($key, $matchedKeys, true);
            if ($isMatched) {
                $lines[] = [
                    'ok' => true,
                    'text' => '✓ '.($rule['label'] ?? $labels['ok']),
                ];

                continue;
            }
            if ($labels['fail'] === null) {
                continue;
            }
            $lines[] = [
                'ok' => false,
                'text' => '✗ '.$labels['fail'],
            ];
        }

        foreach ($warnings as $warning) {
            if ($warning === 'مادة مختلفة') {
                $lines[] = ['ok' => false, 'text' => '✗ ليس نفس المادة'];
            }
            if ($warning === 'مرحلة مختلفة') {
                $lines[] = ['ok' => false, 'text' => '✗ ليس نفس المرحلة'];
            }
        }

        $seen = [];
        $unique = [];
        foreach ($lines as $line) {
            if (isset($seen[$line['text']])) {
                continue;
            }
            $seen[$line['text']] = true;
            $unique[] = $line;
        }

        return $unique;
    }

    protected function mergeRules(array $defaults, array $incoming): array
    {
        $merged = $defaults;
        foreach (['balance_penalty_per_point', 'week_penalty_per_coverage', 'max_daily_substitute_periods'] as $key) {
            if (array_key_exists($key, $incoming)) {
                $merged[$key] = $incoming[$key];
            }
        }

        $defaultRules = $defaults['rules'] ?? [];
        $incomingRules = $incoming['rules'] ?? $incoming;
        if (isset($incomingRules['same_subject']) || isset($incomingRules[0])) {
            foreach ($defaultRules as $ruleKey => $ruleDefault) {
                $row = $incomingRules[$ruleKey] ?? [];
                $merged['rules'][$ruleKey] = array_merge($ruleDefault, is_array($row) ? $row : []);
            }
        }

        return $merged;
    }

    protected function normalizeBlob(?string $text): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', (string) $text)));
    }

    protected function textsOverlap(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        return str_contains($a, $b) || str_contains($b, $a);
    }
}
