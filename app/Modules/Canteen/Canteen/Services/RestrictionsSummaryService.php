<?php

namespace App\Modules\Canteen\Services;

use App\Modules\Canteen\Models\AuditLog;
use App\Modules\Canteen\Models\RestrictionRule;
use App\Modules\Canteen\Models\StudentRestrictionAssignment;

class RestrictionsSummaryService
{
    /**
     * @return array{active_rules: int, students_with_restrictions: int, restriction_violations: int}
     */
    public function summary(): array
    {
        return [
            'active_rules' => RestrictionRule::query()->where('is_active', true)->count(),
            'students_with_restrictions' => StudentRestrictionAssignment::query()
                ->distinct()
                ->count('student_id_ref'),
            'restriction_violations' => AuditLog::query()
                ->whereIn('action', ['restriction.block_triggered', 'restriction.warning_triggered'])
                ->count(),
        ];
    }
}
