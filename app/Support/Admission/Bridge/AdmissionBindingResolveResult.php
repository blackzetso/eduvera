<?php

namespace App\Support\Admission\Bridge;

use App\Models\Form;

readonly class AdmissionBindingResolveResult
{
    public function __construct(
        public string $status,
        public ?AdmissionBindingDefinition $binding = null,
        public ?Form $form = null,
        public ?string $inactiveReason = null,
        public ?string $errorCode = null,
    ) {}

    public function isResolved(): bool
    {
        return $this->status === BindingResolveStatus::RESOLVED;
    }

    public function isInactive(): bool
    {
        return $this->status === BindingResolveStatus::INACTIVE;
    }

    public function isNotFound(): bool
    {
        return $this->status === BindingResolveStatus::NOT_FOUND;
    }

    public static function notFound(): self
    {
        return new self(
            status: BindingResolveStatus::NOT_FOUND,
            errorCode: BridgeErrorCode::BINDING_NOT_FOUND,
        );
    }

    public static function inactive(
        string $reason,
        string $errorCode = BridgeErrorCode::BINDING_INACTIVE,
        ?AdmissionBindingDefinition $binding = null,
        ?Form $form = null,
    ): self {
        return new self(
            status: BindingResolveStatus::INACTIVE,
            binding: $binding,
            form: $form,
            inactiveReason: $reason,
            errorCode: $errorCode,
        );
    }

    public static function resolved(AdmissionBindingDefinition $binding, Form $form): self
    {
        return new self(
            status: BindingResolveStatus::RESOLVED,
            binding: $binding,
            form: $form,
        );
    }
}
