<?php

namespace App\Modules\Canteen\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CanteenFinanceEntry extends CanteenModel
{
    public const TYPE_PURCHASE = 'canteen_purchase';

    public const TYPE_VOID = 'canteen_void';

    public const TYPE_FAILED = 'canteen_failed';

    public const SCOPE_STUDENT = 'student';

    public const SCOPE_FAMILY = 'family';

    public const DIRECTION_DEBIT = 'debit';

    public const DIRECTION_CREDIT = 'credit';

    public const STATUS_POSTED = 'posted';

    public const STATUS_REVERSED = 'reversed';

    public const STATUS_FAILED = 'failed';

    protected $table = 'canteen_finance_entries';

    protected $fillable = [
        'sale_id', 'entry_type', 'ledger_scope', 'student_user_id', 'student_id_ref',
        'guardian_user_id', 'household_key', 'amount', 'direction', 'currency',
        'wallet_settlement_id', 'wallet_tx_id', 'inventory_transaction_ids', 'status',
        'metadata', 'posted_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'inventory_transaction_ids' => 'array',
        'metadata' => 'array',
        'posted_at' => 'datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function studentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function guardianUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_user_id');
    }

    public function walletSettlement(): BelongsTo
    {
        return $this->belongsTo(WalletReadyTransaction::class, 'wallet_settlement_id');
    }
}
