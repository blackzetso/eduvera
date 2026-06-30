<?php

namespace App\Exceptions\Admission;

use App\Support\Admission\Bridge\BridgeErrorCode;
use RuntimeException;

class BridgeBindingAmbiguousException extends RuntimeException
{
    public function __construct(
        public readonly int $formId,
        public readonly array $bindingKeys,
    ) {
        parent::__construct(
            sprintf(
                'Multiple enabled bindings reference form_id %d: %s',
                $formId,
                implode(', ', $bindingKeys),
            ),
        );
    }

    public function errorCode(): string
    {
        return BridgeErrorCode::BINDING_AMBIGUOUS;
    }
}
