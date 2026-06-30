<?php

namespace App\Modules\Canteen\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends CanteenModel
{
    use SoftDeletes;

    protected $table = 'canteen_categories';

    protected $fillable = [
        'name', 'name_ar', 'slug', 'description', 'sort_order', 'is_active', 'metadata',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'sort_order' => 'integer',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
