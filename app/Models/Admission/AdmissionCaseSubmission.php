<?php

namespace App\Models\Admission;

use App\Models\FormSubmission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionCaseSubmission extends Model
{
    protected $fillable = [
        'admission_application_id',
        'form_submission_id',
        'correlation_id',
    ];

    public function admissionApplication(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class);
    }

    public function formSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class);
    }
}
