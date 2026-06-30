<?php

namespace App\Models\Website;

use App\Models\Concerns\AutoTranslatesBilingualFields;
use Illuminate\Database\Eloquent\Model;

class WebsiteNavLink extends Model
{
    use AutoTranslatesBilingualFields;
    protected $fillable = ['label', 'label_ar', 'href', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
