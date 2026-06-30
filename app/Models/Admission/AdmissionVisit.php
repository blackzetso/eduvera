<?php

namespace App\Models\Admission;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionVisit extends Model
{
    protected $fillable = [
        'admission_application_id',
        'scheduled_date',
        'scheduled_time',
        'status',
        'outcome',
        'attendance_status',
        'completed_at',
        'notes',
        'follow_up_notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }
}
