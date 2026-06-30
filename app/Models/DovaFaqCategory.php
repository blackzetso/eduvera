<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DovaFaqCategory extends Model
{
    protected $fillable = [
        'slug',
        'name_en',
        'name_ar',
        'is_system',
        'sort_order',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function faqs(): HasMany
    {
        return $this->hasMany(DovaFaq::class, 'category_id');
    }
}
