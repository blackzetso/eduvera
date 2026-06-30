<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WebsiteLandingSection extends Model
{
    protected $fillable = [
        'page_id',
        'uuid',
        'block_type',
        'admin_name',
        'anchor_id',
        'sort_order',
        'is_enabled',
        'is_visible',
        'settings',
        'content',
        'show_desktop',
        'show_tablet',
        'show_mobile',
        'scheduled_starts_at',
        'scheduled_ends_at',
        'duplicated_from_id',
    ];

    protected $casts = [
        'settings' => 'array',
        'content' => 'array',
        'is_enabled' => 'boolean',
        'is_visible' => 'boolean',
        'show_desktop' => 'boolean',
        'show_tablet' => 'boolean',
        'show_mobile' => 'boolean',
        'scheduled_starts_at' => 'datetime',
        'scheduled_ends_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $section) {
            if (empty($section->uuid)) {
                $section->uuid = (string) Str::uuid();
            }
        });
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(WebsiteLandingPage::class, 'page_id');
    }

    public function duplicatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicated_from_id');
    }

    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'duplicated_from_id' => $this->duplicated_from_id,
            'block_type' => $this->block_type,
            'admin_name' => $this->admin_name,
            'anchor_id' => $this->anchor_id,
            'sort_order' => $this->sort_order,
            'is_enabled' => $this->is_enabled,
            'is_visible' => $this->is_visible,
            'settings' => $this->settings ?? [],
            'content' => $this->content ?? [],
            'show_desktop' => $this->show_desktop,
            'show_tablet' => $this->show_tablet,
            'show_mobile' => $this->show_mobile,
            'scheduled_starts_at' => $this->scheduled_starts_at?->toIso8601String(),
            'scheduled_ends_at' => $this->scheduled_ends_at?->toIso8601String(),
        ];
    }
}
