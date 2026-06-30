<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceCardReader extends Model
{
    protected $fillable = [
        'name',
        'location',
        'device_id',
        'api_key_hash',
        'session_type',
        'default_status',
        'late_after_time',
        'is_active',
        'last_seen_at',
        'settings_json',
    ];

    protected $hidden = [
        'api_key_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'settings_json' => 'array',
        ];
    }
}
