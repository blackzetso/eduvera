<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveStreamQuizAnswer extends Model
{
    protected $fillable = [
        'live_stream_quiz_id',
        'live_stream_id',
        'student_name',
        'student_identifier',
        'answer',
        'correction',
        'is_correct',
        'submitted_at',
    ];

    protected $casts = [
        'is_correct'   => 'boolean',
        'submitted_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(LiveStreamQuiz::class, 'live_stream_quiz_id');
    }

    public function liveStream(): BelongsTo
    {
        return $this->belongsTo(LiveStream::class);
    }
}
