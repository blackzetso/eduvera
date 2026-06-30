<?php

namespace App\Support\Admission\Bridge;

class BridgeRunOutcome
{
    public const CASE_CREATED = 'case_created';

    public const CASE_LINKED = 'case_linked';

    public const NO_OP = 'no_op';

    public static function all(): array
    {
        return [
            self::CASE_CREATED,
            self::CASE_LINKED,
            self::NO_OP,
        ];
    }
}
