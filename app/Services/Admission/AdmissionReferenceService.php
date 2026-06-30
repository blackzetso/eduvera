<?php

namespace App\Services\Admission;

use App\Models\Admission\AdmissionApplication;

class AdmissionReferenceService
{
    /**
     * Generate a unique admissions reference code.
     *
     * Format: ADM-{YEAR}-{SEQUENCE}
     * Example: ADM-2026-00001
     */
    public function generate(?int $year = null): string
    {
        $year ??= now()->year;
        $prefix = config('admissions.reference_prefix', 'ADM')."-{$year}-";

        $lastCode = AdmissionApplication::query()
            ->where('reference_code', 'like', $prefix.'%')
            ->orderByDesc('reference_code')
            ->value('reference_code');

        $sequence = 1;
        if ($lastCode && preg_match('/-(\d+)$/', $lastCode, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        $code = $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);

        while (AdmissionApplication::query()->where('reference_code', $code)->exists()) {
            $sequence++;
            $code = $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
        }

        return $code;
    }
}
