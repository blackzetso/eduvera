<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DovaVoiceRecognition extends Model
{
    protected $fillable = [
        'user_id',
        'portal',
        'role',
        'success',
        'engine',
        'detected_language',
        'transcript',
        'error_code',
        'duration_ms',
    ];

    protected $casts = [
        'success' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
