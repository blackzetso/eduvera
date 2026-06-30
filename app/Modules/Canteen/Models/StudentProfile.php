<?php

namespace App\Modules\Canteen\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentProfile extends CanteenModel
{
    use SoftDeletes;

    protected $table = 'canteen_student_profiles';

    protected $fillable = [
        'user_id', 'primary_guardian_user_id', 'guardian_id_ref',
        'student_id_ref', 'student_name', 'grade', 'class_name',
        'daily_spending_limit', 'is_active', 'last_synced_at', 'metadata', 'health_restrictions',
        'created_by', 'updated_by',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function primaryGuardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_guardian_user_id');
    }

    protected $casts = [
        'daily_spending_limit' => 'decimal:2',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
        'metadata' => 'array',
        'health_restrictions' => 'array',
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
