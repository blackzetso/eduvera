<?php

namespace App\Support\FormBuilder;

use App\Models\User;

class FormRuntimeContext
{
    public function __construct(
        public string $locale = 'ar',
        public ?int $userId = null,
        public ?string $userType = null,
        public ?string $ipAddress = null,
        public string $channel = 'runtime',
        public bool $enforceAccess = true,
        public ?User $user = null,
    ) {}

    public static function forUser(User $user, string $locale = 'ar', string $channel = 'portal'): self
    {
        return new self(
            locale: $locale,
            userId: $user->id,
            userType: $user->user_type,
            channel: $user->isAdmin() ? 'admin' : $channel,
            user: $user,
        );
    }

    public static function preview(string $locale = 'ar'): self
    {
        return new self(
            locale: $locale,
            channel: 'preview',
            enforceAccess: false,
        );
    }

    public static function anonymous(string $locale = 'ar', ?string $ipAddress = null): self
    {
        return new self(
            locale: $locale,
            ipAddress: $ipAddress,
            channel: 'public',
        );
    }

    public static function authenticated(
        int $userId,
        ?string $userType = null,
        string $locale = 'ar',
    ): self {
        return new self(
            locale: $locale,
            userId: $userId,
            userType: $userType,
            channel: 'portal',
        );
    }

    public function resolvedLocale(): string
    {
        return in_array($this->locale, ['ar', 'en'], true) ? $this->locale : 'ar';
    }
}
