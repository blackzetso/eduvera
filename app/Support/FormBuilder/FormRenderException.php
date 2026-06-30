<?php

namespace App\Support\FormBuilder;

use RuntimeException;

class FormRenderException extends RuntimeException
{
    public static function notRenderable(string $message): self
    {
        return new self($message);
    }
}
