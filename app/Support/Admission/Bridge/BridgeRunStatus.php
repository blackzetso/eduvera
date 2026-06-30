<?php

namespace App\Support\Admission\Bridge;

class BridgeRunStatus
{
    public const PENDING = 'pending';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    public static function all(): array
    {
        return [
            self::PENDING,
            self::COMPLETED,
            self::FAILED,
            self::SKIPPED,
        ];
    }
}
