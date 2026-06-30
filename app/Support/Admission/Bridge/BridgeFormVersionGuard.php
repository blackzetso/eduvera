<?php

namespace App\Support\Admission\Bridge;

class BridgeFormVersionGuard
{
    public function matches(AdmissionBindingDefinition $binding, int $snapshotFormVersion): bool
    {
        if ($binding->mappedFormVersion === null) {
            return false;
        }

        return $binding->mappedFormVersion === $snapshotFormVersion;
    }

    public function mismatchErrorCode(): string
    {
        return BridgeErrorCode::MAP_VERSION_MISMATCH;
    }

    public function expectedVersion(AdmissionBindingDefinition $binding): ?int
    {
        return $binding->mappedFormVersion;
    }
}
