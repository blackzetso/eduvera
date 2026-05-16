<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletTransaction extends Model
{
    protected $fillable = [
        'wallet_id',
        'type',
        'amount',
        'description',
        'related_type',
        'related_id',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:6',
        'metadata' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(StorageWallet::class, 'wallet_id');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function isCredit(): bool
    {
        return $this->type === 'credit';
    }

    public function isDebit(): bool
    {
        return $this->type === 'debit';
    }
}

