<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Timetable extends Model
{
    protected $fillable = [
        'name',
        'academic_year',
        'status',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function days(): HasMany
    {
        return $this->hasMany(TimetableDay::class)->orderBy('day_order');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(TimetablePeriod::class);
    }
}
