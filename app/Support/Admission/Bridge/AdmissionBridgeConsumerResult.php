<?php

namespace App\Support\Admission\Bridge;

use App\Models\Admission\AdmissionBridgeRun;

readonly class AdmissionBridgeConsumerResult
{
    public const IGNORED = 'ignored';

    public const SKIPPED = 'skipped';

    public const DEAD_LETTERED = 'dead_lettered';

    public const PENDING = 'pending';

    public const RESUMED = 'resumed';

    public const COMPLETED = 'completed';

    public function __construct(
        public string $status,
        public ?AdmissionBridgeRun $run = null,
        public ?string $reason = null,
    ) {}

    public static function ignored(?string $reason = null): self
    {
        return new self(self::IGNORED, reason: $reason);
    }

    public static function skipped(AdmissionBridgeRun $run, ?string $reason = null): self
    {
        return new self(self::SKIPPED, $run, $reason);
    }

    public static function deadLettered(AdmissionBridgeRun $run): self
    {
        return new self(self::DEAD_LETTERED, $run);
    }

    public static function pending(AdmissionBridgeRun $run): self
    {
        return new self(self::PENDING, $run);
    }

    public static function resumed(AdmissionBridgeRun $run): self
    {
        return new self(self::RESUMED, $run);
    }

    public static function completed(AdmissionBridgeRun $run): self
    {
        return new self(self::COMPLETED, $run);
    }
}
