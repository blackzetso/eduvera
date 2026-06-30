<?php

namespace App\Support\Student;

class StudentStatus
{
    public const ACTIVE = 'active';

    public const PENDING = 'pending';

    public const SUSPENDED = 'suspended';

    public const WITHDRAWN = 'withdrawn';

    public const GRADUATED = 'graduated';

    public const TRANSFERRED = 'transferred';

    public static function all(): array
    {
        return config('student.statuses', []);
    }

    public static function label(?string $status): string
    {
        return match ($status) {
            self::ACTIVE => 'نشط',
            self::PENDING => 'قيد الانتظار',
            self::SUSPENDED => 'موقوف',
            self::WITHDRAWN => 'منسحب',
            self::GRADUATED => 'متخرج',
            self::TRANSFERRED => 'محوّل',
            default => $status ?? '—',
        };
    }

    public static function badgeClass(?string $status): string
    {
        return match ($status) {
            self::ACTIVE => 'bg-success',
            self::PENDING => 'bg-warning text-dark',
            self::SUSPENDED => 'bg-danger',
            self::WITHDRAWN => 'bg-secondary',
            self::GRADUATED => 'bg-info',
            self::TRANSFERRED => 'bg-primary',
            default => 'bg-secondary',
        };
    }

    public static function options(): array
    {
        return collect(self::all())
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => self::label($value),
            ])
            ->values()
            ->all();
    }

    public static function allowedTransitionsFrom(string $from): array
    {
        return match ($from) {
            self::PENDING => [self::ACTIVE],
            self::ACTIVE => [self::SUSPENDED, self::WITHDRAWN, self::GRADUATED],
            self::SUSPENDED => [self::ACTIVE],
            self::TRANSFERRED => [self::ACTIVE],
            default => [],
        };
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::allowedTransitionsFrom($from), true);
    }

    public static function reEnrollEligibleStatuses(): array
    {
        return [self::WITHDRAWN, self::TRANSFERRED, self::PENDING];
    }

    public static function transitionOptions(string $current): array
    {
        return collect(self::allowedTransitionsFrom($current))
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => self::label($value),
            ])
            ->values()
            ->all();
    }
}
