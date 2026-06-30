<?php

namespace App\Modules\Canteen\Support;

final class SaleStatus
{
    public const PENDING_PAYMENT = 'pending_payment';

    public const COMPLETED = 'completed';

    public const VOIDED = 'voided';

    public const FAILED = 'failed';

    /**
     * @return list<string>
     */
    public static function voidable(): array
    {
        return [
            self::PENDING_PAYMENT,
            self::COMPLETED,
        ];
    }
}
