<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceAlert extends Model
{
    protected $fillable = [
        'student_id',
        'academic_year',
        'period_label',
        'level',
        'absences_count',
        'late_count',
        'triggered_at',
        'acknowledged_by',
        'acknowledged_at',
        'action_taken',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'triggered_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
