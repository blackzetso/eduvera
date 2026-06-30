<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DovaFaqFeedback extends Model
{
    protected $table = 'dova_faq_feedback';

    protected $fillable = [
        'query_id',
        'faq_id',
        'helpful',
        'question',
        'portal',
        'role',
        'user_id',
    ];

    protected $casts = [
        'helpful' => 'boolean',
    ];

    public function query(): BelongsTo
    {
        return $this->belongsTo(DovaKnowledgeQuery::class, 'query_id');
    }

    public function faq(): BelongsTo
    {
        return $this->belongsTo(DovaFaq::class, 'faq_id');
    }
}
