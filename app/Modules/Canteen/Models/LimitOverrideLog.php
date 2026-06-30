<?php

namespace App\Modules\Canteen\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LimitOverrideLog extends Model
{
    use HasUuids;

    protected $table = 'canteen_limit_override_logs';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'sale_id', 'student_id_ref', 'attempted_amount', 'daily_limit',
        'remaining_before', 'override_by', 'reason', 'created_at',
    ];

    protected $casts = [
        'attempted_amount' => 'decimal:2',
        'daily_limit' => 'decimal:2',
        'remaining_before' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function overrider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_by');
    }
}
