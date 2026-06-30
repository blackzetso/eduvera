<?php

namespace App\Support\FormBuilder;

use RuntimeException;

class FormSubmissionException extends RuntimeException
{
    public static function validationFailed(FormValidationResult $result): self
    {
        $exception = new self('Form submission validation failed.');
        $exception->validation = $result;

        return $exception;
    }

    public static function snapshotMismatch(string $expected, string $provided): self
    {
        return new self("Form definition changed. Expected snapshot {$expected}, received {$provided}.");
    }

    public static function notAllowed(string $message): self
    {
        return new self($message);
    }

    public static function invalidStatus(string $status): self
    {
        return new self("Invalid submission status: {$status}");
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self("Cannot transition submission from {$from} to {$to}.");
    }

    public static function notFound(): self
    {
        return new self('Form submission not found.');
    }

    public ?FormValidationResult $validation = null;

    public function validationResult(): ?FormValidationResult
    {
        return $this->validation;
    }
}
