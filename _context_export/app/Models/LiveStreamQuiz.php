<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class LiveStreamQuiz extends Model
{
    protected $fillable = [
        'live_stream_id',
        'question_text',
        'question_type',
        'options',
        'correct_answer',
        'allow_multiple',
        'time_limit',
        'status',
        'activated_at',
        'closed_at',
        'sort_order',
        'attachment_path',
    ];

    protected $casts = [
        'options'        => 'array',
        'allow_multiple' => 'boolean',
        'activated_at'   => 'datetime',
        'closed_at'      => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function liveStream(): BelongsTo
    {
        return $this->belongsTo(LiveStream::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(LiveStreamQuizAnswer::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getAttachmentUrlAttribute(): ?string
    {
        if (!$this->attachment_path) {
            return null;
        }
        return Storage::disk('public')->url($this->attachment_path);
    }
}
