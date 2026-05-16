<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveStream extends Model
{
    protected $fillable = [
        'title',
        'description',
        'learning_points',
        'thumbnail_path',
        'teacher_name',
        'teacher_email',
        'subject',
        'provider',
        'classroom_dashboard',
        'teams_meeting_id',
        'zoom_meeting_id',
        'livekit_room_name',
        'google_meet_space_name',
        'hms_room_id',
        'join_url',
        'start_datetime',
        'end_datetime',
        'status',
        'extra_session_status',
        'pending_extension_minutes',
        'category_id',
        'recording_type',
        'recording_status',
        'recording_path',
        'recording_size_mb',
        'video_url',
    ];

    protected $casts = [
        'start_datetime'  => 'datetime',
        'end_datetime'    => 'datetime',
        'learning_points' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(\App\Models\Category::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(LiveStreamAttendance::class);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(LiveStreamQuiz::class);
    }

    public function examSessions(): HasMany
    {
        return $this->hasMany(LiveStreamExamSession::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(LiveStreamReview::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(LiveStreamComment::class)->whereNull('parent_id')->oldest();
    }

    public function getAttendanceCountAttribute(): int
    {
        return $this->attendances()->count();
    }
}
