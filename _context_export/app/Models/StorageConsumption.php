<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageConsumption extends Model
{
    protected $table = 'storage_consumption';

    protected $fillable = [
        'file_id',
        'storage_gb',
        'bandwidth_gb',
        'bunny_cost',
        'platform_cost',
        'last_synced_at',
    ];

    protected $casts = [
        'storage_gb' => 'decimal:4',
        'bandwidth_gb' => 'decimal:4',
        'bunny_cost' => 'decimal:4',
        'platform_cost' => 'decimal:4',
        'last_synced_at' => 'datetime',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function updateConsumption(float $storageGb, float $bandwidthGb, float $bunnyCost, float $platformCost): void
    {
        $this->storage_gb = $storageGb;
        $this->bandwidth_gb = $bandwidthGb;
        $this->bunny_cost = $bunnyCost;
        $this->platform_cost = $platformCost;
        $this->last_synced_at = now();
        $this->save();
    }
}

