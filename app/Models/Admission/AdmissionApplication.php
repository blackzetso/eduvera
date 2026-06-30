<?php

namespace App\Models\Admission;

use App\Models\Admission\AdmissionDecisionHistory;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AdmissionApplication extends Model
{
    protected $fillable = [
        'reference_code',
        'application_group_id',
        'pipeline_stage',
        'status',
        'academic_year',
        'target_category_id',
        'source_channel',
        'source_reference',
        'assigned_to_user_id',
        'priority',
        'notes',
        'decision',
        'decision_at',
        'decision_by_user_id',
        'converted_student_id',
        'converted_at',
        'converted_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'decision_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function isReadOnly(): bool
    {
        return $this->decision === 'converted' || $this->converted_student_id !== null;
    }

    public function applicants(): HasMany
    {
        return $this->hasMany(AdmissionApplicant::class);
    }

    public function primaryApplicant(): HasOne
    {
        return $this->hasOne(AdmissionApplicant::class)->oldestOfMany();
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(AdmissionContact::class);
    }

    public function primaryContact(): HasOne
    {
        return $this->hasOne(AdmissionContact::class)->where('is_primary', true);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(AdmissionVisit::class);
    }

    public function latestVisit(): HasOne
    {
        return $this->hasOne(AdmissionVisit::class)->latestOfMany();
    }

    public function stageHistories(): HasMany
    {
        return $this->hasMany(AdmissionStageHistory::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function targetCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'target_category_id');
    }

    public function convertedStudent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_student_id');
    }

    public function decisionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decision_by_user_id');
    }

    public function convertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by_user_id');
    }

    public function decisionHistories(): HasMany
    {
        return $this->hasMany(AdmissionDecisionHistory::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AdmissionDocument::class);
    }

    public function internalNotes(): HasMany
    {
        return $this->hasMany(AdmissionNote::class);
    }

    public function engagements(): HasMany
    {
        return $this->hasMany(AdmissionEngagement::class);
    }

    public function assignmentHistories(): HasMany
    {
        return $this->hasMany(AdmissionAssignmentHistory::class);
    }
}
