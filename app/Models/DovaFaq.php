<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DovaFaq extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_REVIEW = 'review';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const KNOWLEDGE_ACTIVE = 'active';

    public const KNOWLEDGE_NEEDS_REVIEW = 'needs_review';

    public const KNOWLEDGE_ARCHIVED = 'archived';

    public const KNOWLEDGE_DEPRECATED = 'deprecated';

    protected $fillable = [
        'question_en',
        'question_ar',
        'answer_en',
        'answer_ar',
        'category_id',
        'tags',
        'status',
        'source',
        'knowledge_gap_id',
        'created_by',
        'updated_by',
        'owner_user_id',
        'review_frequency_days',
        'last_reviewed_at',
        'next_review_due_at',
        'knowledge_status',
        'view_count',
        'helpful_count',
        'not_helpful_count',
        'published_at',
        'indexed_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'published_at' => 'datetime',
        'indexed_at' => 'datetime',
        'last_reviewed_at' => 'datetime',
        'next_review_due_at' => 'datetime',
        'review_frequency_days' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(DovaFaqCategory::class, 'category_id');
    }

    public function knowledgeGap(): BelongsTo
    {
        return $this->belongsTo(DovaKnowledgeGap::class, 'knowledge_gap_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isRetrievable(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->knowledge_status !== self::KNOWLEDGE_ARCHIVED;
    }

    public function isOverdueForReview(): bool
    {
        return $this->next_review_due_at !== null
            && $this->next_review_due_at->isPast()
            && $this->knowledge_status === self::KNOWLEDGE_ACTIVE;
    }
}
