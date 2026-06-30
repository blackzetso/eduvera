<?php

namespace App\Modules\Canteen\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentProfile extends CanteenModel
{
    use SoftDeletes;

    protected $table = 'canteen_student_profiles';

    protected $fillable = [
        'student_id_ref', 'student_name', 'grade', 'class_name',
        'daily_spending_limit', 'is_active', 'last_synced_at', 'metadata',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'daily_spending_limit' => 'decimal:2',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function restrictionAssignments(): HasMany
    {
        return $this->hasMany(StudentRestrictionAssignment::class, 'student_id_ref', 'student_id_ref');
    }

    public function blockedProducts(): HasMany
    {
        return $this->hasMany(StudentBlockedProduct::class, 'student_id_ref', 'student_id_ref');
    }

    public function blockedCategories(): HasMany
    {
        return $this->hasMany(StudentBlockedCategory::class, 'student_id_ref', 'student_id_ref');
    }
}
