<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteTestimonial extends Model
{
    protected $fillable = [
        'external_id', 'name', 'role', 'role_type', 'quote',
        'photo_media_id', 'photo_src', 'photo_alt', 'is_active', 'sort_order',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function photoMedia(): BelongsTo
    {
        return $this->belongsTo(WebsiteMedia::class, 'photo_media_id');
    }
}
