<?php

namespace App\Modules\Canteen\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends CanteenModel
{
    protected $table = 'canteen_inventory_transactions';

    protected $fillable = [
        'product_id', 'type', 'quantity_delta', 'unit_cost', 'reference_type',
        'reference_id', 'notes', 'occurred_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'quantity_delta' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'occurred_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
