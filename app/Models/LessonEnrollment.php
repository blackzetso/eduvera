<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LessonEnrollment extends Model
{
    protected $fillable = [
        'student_id',
        'lesson_id',
        'status',
        'enrolled_at',
        'expires_at',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'expires_at'  => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function lectureViews(): HasMany
    {
        return $this->hasMany(LectureView::class, 'enrollment_id');
    }

    /**
     * Percentage of lectures viewed (0–100).
     */
    public function progressPercent(): int
    {
        $total = $this->lesson?->lectures()->count() ?? 0;
        if ($total === 0) {
            return 0;
        }
        $viewed = $this->lectureViews()->count();

        return (int) round(($viewed / $total) * 100);
    }
}
