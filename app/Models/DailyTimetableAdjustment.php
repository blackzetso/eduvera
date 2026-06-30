<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyTimetableAdjustment extends Model
{
    public const SWAP_MOVE = 'move_lesson';

    public const SWAP_EXCHANGE = 'swap_lessons';

    public const SWAP_REPLACE = 'replace_teacher';

    protected $fillable = [
        'adjustment_date',
        'timetable_id',
        'swap_type',
        'teacher_id',
        'timetable_period_id',
        'target_timetable_period_id',
        'secondary_teacher_id',
        'secondary_timetable_period_id',
        'replacement_teacher_id',
        'trigger_period_id',
        'subject_id',
        'category_id',
        'original_period_number',
        'new_period_number',
        'reason',
        'impact_preview',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'adjustment_date' => 'date',
            'impact_preview' => 'array',
        ];
    }

    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(TimetablePeriod::class, 'timetable_period_id');
    }

    public function targetPeriod(): BelongsTo
    {
        return $this->belongsTo(TimetablePeriod::class, 'target_timetable_period_id');
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('adjustment_date', $date);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['draft', 'approved']);
    }
}
