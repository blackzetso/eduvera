<?php

namespace App\Modules\Canteen\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends CanteenModel
{
    use SoftDeletes;

    protected $table = 'canteen_sales';

    protected $fillable = [
        'sale_number', 'student_id_ref', 'student_name', 'grade', 'class_name',
        'subtotal', 'discount', 'total', 'payment_method', 'status',
        'daily_limit_checked', 'restrictions_checked', 'limit_override_applied',
        'limit_override_reason', 'limit_override_by', 'cashier_user_id', 'sold_at',
        'voided_at', 'voided_by', 'void_reason', 'metadata', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'daily_limit_checked' => 'boolean',
        'restrictions_checked' => 'boolean',
        'limit_override_applied' => 'boolean',
        'sold_at' => 'datetime',
        'voided_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }

    public function walletReadyTransaction(): HasOne
    {
        return $this->hasOne(WalletReadyTransaction::class, 'sale_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }
}
