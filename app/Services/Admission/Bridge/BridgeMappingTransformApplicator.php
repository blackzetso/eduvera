<?php

namespace App\Services\Admission\Bridge;

use Carbon\Carbon;

class BridgeMappingTransformApplicator
{
    public function apply(string $transform, mixed $value): mixed
    {
        return match ($transform) {
            'trim' => is_string($value) ? trim($value) : $value,
            'normalize_phone' => $this->normalizePhone($value),
            'lowercase_email' => is_string($value) ? strtolower(trim($value)) : $value,
            'label_only' => is_string($value) ? trim($value) : $value,
            'parse_date' => $this->parseDate($value),
            'parse_time' => $this->parseTime($value),
            default => $value,
        };
    }

    protected function normalizePhone(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '20') && strlen($digits) >= 12) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0')) {
            return '+20'.ltrim($digits, '0');
        }

        return '+'.$digits;
    }

    protected function parseDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse(trim($value))->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseTime(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $trimmed = trim($value);

        if (preg_match('/^\d{2}:\d{2}$/', $trimmed) === 1) {
            return $trimmed;
        }

        try {
            return Carbon::parse($trimmed)->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }
}
