<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DepartmentPlan extends Model
{
    protected $fillable = [
        'timetable_id',
        'name',
        'department_label',
        'status',
        'created_by',
    ];

    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DepartmentPlanItem::class);
    }

    public function staffing(): HasMany
    {
        return $this->hasMany(DepartmentPlanStaffing::class);
    }
}
