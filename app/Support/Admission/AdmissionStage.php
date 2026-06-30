<?php

namespace App\Support\Admission;

class AdmissionStage
{
    public const LEAD = 'lead';

    public const INQUIRY = 'inquiry';

    public const CAMPUS_VISIT = 'campus_visit';

    public const APPLICATION = 'application';

    public static function phaseA(): array
    {
        return [
            self::LEAD,
            self::INQUIRY,
            self::CAMPUS_VISIT,
            self::APPLICATION,
        ];
    }

    public static function options(): array
    {
        return collect(self::phaseA())
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => self::label($value),
            ])
            ->values()
            ->all();
    }

    public static function label(string $stage): string
    {
        $locale = app()->getLocale();
        $key = str_starts_with($locale, 'ar') ? 'label_ar' : 'label_en';

        return config("admissions.stages.{$stage}.{$key}")
            ?? config("admissions.stages.{$stage}.label_en")
            ?? $stage;
    }

    public static function isValid(string $stage): bool
    {
        return in_array($stage, self::phaseA(), true);
    }

    public static function canTransition(string $from, string $to): bool
    {
        if (! self::isValid($from) || ! self::isValid($to)) {
            return false;
        }

        $order = array_flip(self::phaseA());

        return ($order[$to] ?? -1) >= ($order[$from] ?? -1);
    }

    public static function forwardOptions(string $currentStage): array
    {
        $order = self::phaseA();
        $currentIndex = array_search($currentStage, $order, true);

        if ($currentIndex === false) {
            return self::options();
        }

        return collect($order)
            ->slice($currentIndex)
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => self::label($value),
            ])
            ->values()
            ->all();
    }
}
