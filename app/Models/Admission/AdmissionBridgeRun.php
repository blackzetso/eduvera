<?php

namespace App\Models\Admission;

use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionBridgeRun extends Model
{
    protected $fillable = [
        'submission_id',
        'correlation_id',
        'form_id',
        'binding_key',
        'mapped_form_version',
        'mapping_profile',
        'status',
        'outcome',
        'admission_case_id',
        'error_code',
        'duration_ms',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'mapped_form_version' => 'integer',
            'duration_ms' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'submission_id');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function admissionCase(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_case_id');
    }
}
