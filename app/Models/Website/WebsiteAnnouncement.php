<?php

namespace App\Models\Website;

use App\Models\Concerns\AutoTranslatesBilingualFields;
use Illuminate\Database\Eloquent\Model;

class WebsiteAnnouncement extends Model
{
    use AutoTranslatesBilingualFields;
    protected $fillable = ['external_id', 'text', 'text_ar', 'href', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
