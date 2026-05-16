<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletRechargeRequest extends Model
{
    protected $fillable = [
        'wallet_id',
        'amount',
        'currency',
        'status',
        'payment_gateway',
        'payment_method_id',
        'transaction_id',
        'gateway_response',
    ];

    protected $casts = [
        'amount' => 'decimal:6',
        'gateway_response' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(StorageWallet::class, 'wallet_id');
    }

    public function markAsCompleted(string $transactionId, array $response = []): void
    {
        $this->status = 'completed';
        $this->transaction_id = $transactionId;
        $this->gateway_response = $response;
        $this->save();

        // Credit the wallet
        $this->wallet->credit(
            $this->amount,
            "Wallet recharge via {$this->payment_gateway}",
            $this,
            ['transaction_id' => $transactionId]
        );
    }

    public function markAsFailed(array $response = []): void
    {
        $this->status = 'failed';
        $this->gateway_response = $response;
        $this->save();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}

