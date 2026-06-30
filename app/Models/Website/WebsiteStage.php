<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteStage extends Model
{
    protected $fillable = [
        'slug', 'title', 'subtitle', 'age_range', 'tagline', 'tone',
        'student_count', 'class_size', 'key_skills',
        'image_media_id', 'image_src', 'image_alt', 'payload',
        'is_active', 'sort_order',
    ];

    protected $casts = [
        'key_skills' => 'array',
        'payload' => 'array',
        'is_active' => 'boolean',
    ];

    public function imageMedia(): BelongsTo
    {
        return $this->belongsTo(WebsiteMedia::class, 'image_media_id');
    }
}
