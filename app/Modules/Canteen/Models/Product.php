<?php

namespace App\Modules\Canteen\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends CanteenModel
{
    use SoftDeletes;

    protected $table = 'canteen_products';

    protected $fillable = [
        'category_id', 'sku', 'barcode', 'name', 'name_ar', 'description', 'unit',
        'selling_price', 'cost_price', 'is_active', 'is_restricted_default',
        'restriction_tags', 'image_path', 'metadata', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_restricted_default' => 'boolean',
        'restriction_tags' => 'array',
        'metadata' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'product_id');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'product_id');
    }
}
