<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DovaKnowledgeQuery extends Model
{
    protected $fillable = [
        'question',
        'normalized_question',
        'portal',
        'role',
        'input_method',
        'detected_language',
        'user_id',
        'answered',
        'intent',
        'source_slug',
        'record_key',
        'confidence',
        'response_ms',
        'matched_content',
        'answer_preview',
    ];

    protected $casts = [
        'answered' => 'boolean',
        'confidence' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
