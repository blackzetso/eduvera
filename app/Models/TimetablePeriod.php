<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimetablePeriod extends Model
{
    protected $fillable = [
        'timetable_id',
        'timetable_day_id',
        'period_number',
        'time_from',
        'time_to',
        'category_id',
    ];

    protected $casts = [
        'time_from' => 'string',
        'time_to' => 'string',
    ];

    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class);
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(TimetableDay::class, 'timetable_day_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TimetableAssignment::class);
    }

    public function lessons()
    {
        return $this->belongsToMany(Lesson::class, 'lesson_timetable_period');
    }
}
