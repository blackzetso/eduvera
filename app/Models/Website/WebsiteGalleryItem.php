<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteGalleryItem extends Model
{
    protected $fillable = [
        'external_id', 'category', 'is_featured', 'image_media_id', 'src', 'alt', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function imageMedia(): BelongsTo
    {
        return $this->belongsTo(WebsiteMedia::class, 'image_media_id');
    }
}
