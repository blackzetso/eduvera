<?php

namespace App\Models\Admission;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionApplicant extends Model
{
    protected $fillable = [
        'admission_application_id',
        'first_name',
        'father_name',
        'grandfather_name',
        'date_of_birth',
        'gender',
        'national_id',
        'current_grade_label',
        'target_stage_label',
        'target_category_id',
        'notes',
        'existing_student_user_id',
        'converted_user_id',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }

    public function targetCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'target_category_id');
    }

    public function existingStudent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'existing_student_user_id');
    }

    public function convertedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_user_id');
    }

    public function displayName(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->father_name,
            $this->grandfather_name,
        ])));
    }
}
