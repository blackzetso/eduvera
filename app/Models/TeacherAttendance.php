<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAttendance extends Model
{
    protected $fillable = [
        'teacher_id',
        'attendance_date',
        'status',
        'reason',
        'notes',
        'source',
        'recorded_by',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'metadata_json' => 'array',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeUnavailableOnDate($query, $date)
    {
        return $query
            ->whereDate('attendance_date', $date)
            ->whereIn('status', config('attendance.teacher_unavailable_statuses', []));
    }
}
