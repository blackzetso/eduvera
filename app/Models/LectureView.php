<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LectureView extends Model
{
    protected $fillable = [
        'enrollment_id',
        'lecture_id',
        'first_viewed_at',
        'last_viewed_at',
    ];

    protected $casts = [
        'first_viewed_at' => 'datetime',
        'last_viewed_at'  => 'datetime',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(LessonEnrollment::class, 'enrollment_id');
    }

    public function lecture(): BelongsTo
    {
        return $this->belongsTo(Lecture::class);
    }
}
