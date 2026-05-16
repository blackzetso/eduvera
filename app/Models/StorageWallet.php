<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorageWallet extends Model
{
    protected $fillable = [
        'balance',
        'total_credited',
        'total_debited',
        'last_synced_storage_gb',
        'last_synced_bandwidth_gb',
        'last_synced_at',
        'is_activated',
        'initial_credit_granted',
        'activated_at',
    ];

    protected $casts = [
        'balance' => 'decimal:6',
        'total_credited' => 'decimal:6',
        'total_debited' => 'decimal:6',
        'last_synced_storage_gb' => 'decimal:4',
        'last_synced_bandwidth_gb' => 'decimal:4',
        'is_activated' => 'boolean',
        'initial_credit_granted' => 'boolean',
        'activated_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'wallet_id');
    }

    public function rechargeRequests(): HasMany
    {
        return $this->hasMany(WalletRechargeRequest::class, 'wallet_id');
    }

    public function hasBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }

    public function deduct(float $amount, string $description, $related = null, array $metadata = []): WalletTransaction
    {
        $this->balance -= $amount;
        $this->total_debited += $amount;
        $this->save();

        return $this->transactions()->create([
            'type' => 'debit',
            'amount' => $amount,
            'description' => $description,
            'related_type' => $related ? get_class($related) : null,
            'related_id' => $related?->id,
            'metadata' => $metadata,
        ]);
    }

    public function credit(float $amount, string $description, $related = null, array $metadata = []): WalletTransaction
    {
        $this->balance += $amount;
        $this->total_credited += $amount;
        $this->save();

        return $this->transactions()->create([
            'type' => 'credit',
            'amount' => $amount,
            'description' => $description,
            'related_type' => $related ? get_class($related) : null,
            'related_id' => $related?->id,
            'metadata' => $metadata,
        ]);
    }

    public function activate(): void
    {
        if (!$this->is_activated) {
            $this->is_activated = true;
            $this->activated_at = now();
            $this->save();
        }
    }

    public function grantInitialCredit(float $amount = 20.00): void
    {
        if (!$this->initial_credit_granted) {
            $this->credit($amount, 'Initial free credit', null, ['type' => 'initial_bonus']);
            $this->initial_credit_granted = true;
            $this->save();
        }
    }
}

