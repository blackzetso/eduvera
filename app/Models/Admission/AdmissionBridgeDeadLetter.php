<?php

namespace App\Models\Admission;

use Illuminate\Database\Eloquent\Model;

class AdmissionBridgeDeadLetter extends Model
{
    protected $fillable = [
        'submission_id',
        'correlation_id',
        'form_id',
        'binding_key',
        'error_code',
        'error_message',
        'retry_count',
        'event_payload',
        'failed_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'submission_id' => 'integer',
            'form_id' => 'integer',
            'retry_count' => 'integer',
            'event_payload' => 'array',
            'failed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
