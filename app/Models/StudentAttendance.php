<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAttendance extends Model
{
    protected $fillable = [
        'student_id',
        'category_id',
        'attendance_date',
        'session_type',
        'session_label',
        'timetable_period_id',
        'subject_id',
        'period_number',
        'live_stream_id',
        'status',
        'arrival_time',
        'minutes_late',
        'excused_reason',
        'notes',
        'source',
        'recorded_by',
        'card_reader_id',
        'import_batch_id',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'metadata_json' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function timetablePeriod(): BelongsTo
    {
        return $this->belongsTo(TimetablePeriod::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function liveStream(): BelongsTo
    {
        return $this->belongsTo(LiveStream::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(AttendanceImportBatch::class, 'import_batch_id');
    }

    public function scopeExcludingLiveStreamForThresholds($query)
    {
        return $query->where('session_type', '!=', 'live_stream');
    }

    public function scopeAbsentOrLate($query)
    {
        return $query->whereIn('status', ['absent', 'late']);
    }
}
