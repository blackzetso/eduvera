<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DovaKnowledgeGap extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'topic',
        'topic_slug',
        'frequency',
        'first_asked_at',
        'last_asked_at',
        'portal',
        'role',
        'suggested_category',
        'status',
        'priority',
        'sample_questions',
        'resolved_faq_id',
        'resolved_at',
    ];

    protected $casts = [
        'sample_questions' => 'array',
        'first_asked_at' => 'datetime',
        'last_asked_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function resolutionStatus(): string
    {
        if ($this->status === self::STATUS_DISMISSED) {
            return 'ignored';
        }

        if ($this->status === self::STATUS_RESOLVED) {
            return 'published';
        }

        $faq = $this->relationLoaded('faq') ? $this->faq : $this->faq()->first();

        if ($faq) {
            return match ($faq->status) {
                DovaFaq::STATUS_REVIEW => 'pending_review',
                DovaFaq::STATUS_PUBLISHED => 'published',
                default => 'draft',
            };
        }

        return 'unanswered';
    }

    public function resolvedFaq(): BelongsTo
    {
        return $this->belongsTo(DovaFaq::class, 'resolved_faq_id');
    }

    public function faq(): HasOne
    {
        return $this->hasOne(DovaFaq::class, 'knowledge_gap_id');
    }
}
