<?php

namespace App\Support\Student;

class GuardianRelationship
{
    public const FATHER = 'father';

    public const MOTHER = 'mother';

    public const GUARDIAN = 'guardian';

    public static function types(): array
    {
        return config('student.guardian_relationship_types', []);
    }

    public static function typeLabel(?string $type): string
    {
        return match ($type) {
            self::FATHER => 'أب',
            self::MOTHER => 'أم',
            self::GUARDIAN => 'ولي أمر',
            default => $type ?? '—',
        };
    }

    public static function roleLabels(array $pivot): array
    {
        $labels = [self::typeLabel($pivot['relationship_type'] ?? null)];

        if (! empty($pivot['is_primary'])) {
            $labels[] = 'ولي أمر أساسي';
        }
        if (! empty($pivot['is_emergency_contact'])) {
            $labels[] = 'جهة طوارئ';
        }
        if (! empty($pivot['is_pickup_authorized'])) {
            $labels[] = 'مخوّل بالاستلام';
        }
        if (! empty($pivot['is_financial_responsible'])) {
            $labels[] = 'مسؤول مالي';
        }

        return array_values(array_unique(array_filter($labels)));
    }

    public static function typeOptions(): array
    {
        return collect(self::types())
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => self::typeLabel($value),
            ])
            ->values()
            ->all();
    }
}
