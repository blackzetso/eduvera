<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceThreshold extends Model
{
    protected $fillable = [
        'category_id',
        'academic_year',
        'period_type',
        'warning_absences',
        'critical_absences',
        'warning_late_count',
        'critical_late_count',
        'auto_notify_guardian',
        'suggest_block_at_critical',
        'affects_grade_eligibility',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'auto_notify_guardian' => 'boolean',
            'suggest_block_at_critical' => 'boolean',
            'affects_grade_eligibility' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
