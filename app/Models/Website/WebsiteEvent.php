<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteEvent extends Model
{
    protected $fillable = [
        'external_id', 'slug', 'title', 'type', 'date', 'date_short',
        'audience', 'location', 'description', 'cta', 'href',
        'is_open_day', 'limited_seats_label',
        'image_media_id', 'image_src', 'image_alt', 'is_active', 'sort_order',
    ];

    protected $casts = ['is_active' => 'boolean', 'is_open_day' => 'boolean'];

    public function imageMedia(): BelongsTo
    {
        return $this->belongsTo(WebsiteMedia::class, 'image_media_id');
    }
}
