<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableDailyCoverage extends Model
{
    protected $fillable = [
        'coverage_date',
        'timetable_id',
        'timetable_period_id',
        'absent_teacher_id',
        'replacement_teacher_id',
        'subject_id',
        'category_id',
        'reason',
        'match_score',
        'match_reasons',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'coverage_date' => 'date',
            'match_reasons' => 'array',
        ];
    }

    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(TimetablePeriod::class, 'timetable_period_id');
    }

    public function absentTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'absent_teacher_id');
    }

    public function replacementTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replacement_teacher_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('coverage_date', $date);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['draft', 'approved']);
    }
}
