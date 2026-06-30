<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserWallet extends Model
{
    protected $fillable = [
        'user_id',
        'balance',
        'total_credited',
        'total_debited',
    ];

    protected $casts = [
        'balance'        => 'decimal:2',
        'total_credited' => 'decimal:2',
        'total_debited'  => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(UserWalletTransaction::class, 'wallet_id');
    }

    public function credit(float $amount, string $description, ?int $fromWalletId = null): UserWalletTransaction
    {
        $this->increment('balance', $amount);
        $this->increment('total_credited', $amount);

        return $this->transactions()->create([
            'type'           => $fromWalletId ? 'transfer_in' : 'credit',
            'amount'         => $amount,
            'description'    => $description,
            'from_wallet_id' => $fromWalletId,
        ]);
    }

    public function debit(float $amount, string $description, ?int $toWalletId = null): UserWalletTransaction
    {
        $this->decrement('balance', $amount);
        $this->increment('total_debited', $amount);

        return $this->transactions()->create([
            'type'         => $toWalletId ? 'transfer_out' : 'debit',
            'amount'       => $amount,
            'description'  => $description,
            'to_wallet_id' => $toWalletId,
        ]);
    }

    /**
     * Transfer money from this wallet to another wallet.
     */
    public function transferTo(UserWallet $target, float $amount, string $description): void
    {
        $this->debit($amount, $description, $target->id);
        $target->credit($amount, $description, $this->id);
    }

    public function hasBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }
}
