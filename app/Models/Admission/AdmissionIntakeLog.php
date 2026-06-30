<?php

namespace App\Models\Admission;

use Illuminate\Database\Eloquent\Model;

class AdmissionIntakeLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ip_address',
        'user_agent',
        'endpoint',
        'status',
        'rejection_reason',
        'email',
        'phone',
        'application_id',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
