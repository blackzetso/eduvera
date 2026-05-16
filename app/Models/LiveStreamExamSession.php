<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveStreamExamSession extends Model
{
    protected $fillable = [
        'live_stream_id',
        'time_limit',
        'status',
        'activated_at',
        'closed_at',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'closed_at'    => 'datetime',
    ];

    public function liveStream(): BelongsTo
    {
        return $this->belongsTo(LiveStream::class);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(LiveStreamQuiz::class, 'exam_session_id')->orderBy('sort_order')->orderBy('id');
    }

    public function remainingSeconds(): int
    {
        if ($this->status === 'closed') return 0;
        $elapsed = now()->diffInSeconds($this->activated_at, false);
        return max(0, $this->time_limit - $elapsed);
    }
}
