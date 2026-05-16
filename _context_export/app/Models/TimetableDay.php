<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimetableDay extends Model
{
    protected $fillable = [
        'timetable_id',
        'day_name',
        'day_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(TimetablePeriod::class);
    }
}
