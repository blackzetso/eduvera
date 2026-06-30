<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DovaAiUsageLog extends Model
{
    protected $fillable = [
        'model',
        'request_type',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'estimated_cost',
        'response_ms',
        'portal',
        'role',
        'user_id',
        'question',
        'success',
        'used_fallback',
    ];

    protected $casts = [
        'estimated_cost' => 'float',
        'success' => 'boolean',
        'used_fallback' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
