<?php

namespace App\Models\Website;

use App\Models\Concerns\AutoTranslatesBilingualFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsitePost extends Model
{
    use AutoTranslatesBilingualFields;
    protected $fillable = [
        'type', 'external_id', 'slug', 'title', 'title_ar', 'category', 'published_at',
        'summary', 'summary_ar', 'content', 'content_ar', 'is_featured',
        'image_media_id', 'image_src', 'image_alt', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function imageMedia(): BelongsTo
    {
        return $this->belongsTo(WebsiteMedia::class, 'image_media_id');
    }
}
