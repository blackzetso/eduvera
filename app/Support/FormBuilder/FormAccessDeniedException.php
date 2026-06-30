<?php

namespace App\Support\FormBuilder;

use RuntimeException;

class FormAccessDeniedException extends RuntimeException
{
    public function __construct(
        string $message,
        public string $reason = 'access_denied',
    ) {
        parent::__construct($message);
    }

    public static function denied(string $message, string $reason = 'access_denied'): self
    {
        return new self($message, $reason);
    }
}
