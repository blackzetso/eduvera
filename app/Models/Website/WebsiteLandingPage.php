<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsiteLandingPage extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'status',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(WebsiteLandingSection::class, 'page_id')->orderBy('sort_order');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(WebsiteLandingSectionRevision::class, 'page_id')->orderByDesc('version');
    }
}
