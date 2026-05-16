<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = [
        'from_currency',
        'to_currency',
        'rate',
        'source',
        'fetched_at',
        'is_active',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'fetched_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the active exchange rate for a currency pair
     */
    public static function getActiveRate(string $from = 'USD', string $to = 'EGP'): ?float
    {
        $rate = self::where('from_currency', $from)
            ->where('to_currency', $to)
            ->where('is_active', true)
            ->latest('fetched_at')
            ->first();

        return $rate ? (float) $rate->rate : null;
    }

    /**
     * Update or create an exchange rate
     */
    public static function updateRate(string $from, string $to, float $rate, string $source = 'api'): self
    {
        // Deactivate old rates
        self::where('from_currency', $from)
            ->where('to_currency', $to)
            ->update(['is_active' => false]);

        // Create new active rate
        return self::create([
            'from_currency' => $from,
            'to_currency' => $to,
            'rate' => $rate,
            'source' => $source,
            'fetched_at' => now(),
            'is_active' => true,
        ]);
    }
}
