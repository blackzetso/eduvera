<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuardianNotificationPreference extends Model
{
    protected $fillable = [
        'guardian_id',
        'student_id',
        'notify_absence',
        'notify_late',
        'notify_whatsapp',
        'notify_email',
        'notify_in_app',
        'notify_canteen_purchase',
    ];

    protected function casts(): array
    {
        return [
            'notify_absence' => 'boolean',
            'notify_late' => 'boolean',
            'notify_whatsapp' => 'boolean',
            'notify_email' => 'boolean',
            'notify_in_app' => 'boolean',
            'notify_canteen_purchase' => 'boolean',
        ];
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
