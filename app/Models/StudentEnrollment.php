<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEnrollment extends Model
{
    protected $fillable = [
        'student_id',
        'academic_year',
        'category_id',
        'stage_category_id',
        'stage_name',
        'grade_name',
        'class_name',
        'enrollment_date',
        'promotion_date',
        'withdrawal_date',
        'status',
        'action_type',
        'reason',
        'notes',
        'is_current',
        'source',
        'admission_reference_id',
        'performed_by_user_id',
    ];

    protected $casts = [
        'enrollment_date' => 'date',
        'promotion_date' => 'date',
        'withdrawal_date' => 'date',
        'is_current' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stageCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'stage_category_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }
}
