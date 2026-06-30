<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentPlanStaffing extends Model
{
    protected $table = 'department_plan_staffing';

    protected $fillable = [
        'department_plan_id',
        'teacher_id',
        'subject_id',
        'category_id',
        'allocated_periods',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(DepartmentPlan::class, 'department_plan_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
