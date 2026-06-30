<?php

namespace App\Support\Admission\Bridge;

readonly class BridgeCampusVisitOrchestratorResult
{
    public function __construct(
        public bool $success,
        public ?int $admissionCaseId,
        public ?string $outcome,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {}

    public static function succeeded(int $admissionCaseId, string $outcome): self
    {
        return new self(
            success: true,
            admissionCaseId: $admissionCaseId,
            outcome: $outcome,
        );
    }

    public static function failed(string $errorCode, string $errorMessage): self
    {
        return new self(
            success: false,
            admissionCaseId: null,
            outcome: null,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
        );
    }
}
