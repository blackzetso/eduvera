<?php

namespace App\Modules\Canteen\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletReadyTransaction extends CanteenModel
{
    protected $table = 'canteen_wallet_ready_transactions';

    protected $fillable = [
        'sale_id', 'student_id_ref', 'transaction_type', 'amount', 'currency',
        'status', 'source_module', 'external_wallet_tx_id', 'idempotency_key',
        'failure_reason', 'posted_at', 'metadata', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'posted_at' => 'datetime',
        'metadata' => 'array',
        'external_wallet_tx_id' => 'integer',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
}
