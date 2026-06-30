<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentPlanItem extends Model
{
    protected $fillable = [
        'department_plan_id',
        'subject_id',
        'category_id',
        'required_periods',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(DepartmentPlan::class, 'department_plan_id');
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
