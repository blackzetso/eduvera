<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableAssignment extends Model
{
    protected $fillable = [
        'timetable_period_id',
        'teacher_id',
        'subject_id',
        'assigned_by',
        'status',
        'type',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(TimetablePeriod::class, 'timetable_period_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
