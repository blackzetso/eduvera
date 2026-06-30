<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteFacility extends Model
{
    protected $fillable = [
        'external_id', 'icon', 'name', 'description', 'benefit',
        'image_media_id', 'image_src', 'image_alt', 'is_active', 'sort_order',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function imageMedia(): BelongsTo
    {
        return $this->belongsTo(WebsiteMedia::class, 'image_media_id');
    }
}
