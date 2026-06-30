<?php

namespace App\Services;

use App\Models\User;

class StudentCodeService
{
    /**
     * Generate a unique, human-readable student code.
     *
     * Format: STU-{YEAR}-{SEQUENCE}
     * Example: STU-2026-00042
     *
     * - STU prefix scopes codes to students (card readers, imports, reports)
     * - YEAR aids archival search and batch operations
     * - 5-digit sequence supports 99,999 students per year
     */
    public function generate(): string
    {
        $year = now()->year;
        $prefix = "STU-{$year}-";

        $lastCode = User::query()
            ->where('user_type', 'student')
            ->where('student_code', 'like', $prefix.'%')
            ->orderByDesc('student_code')
            ->value('student_code');

        $sequence = 1;
        if ($lastCode && preg_match('/-(\d+)$/', $lastCode, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        $code = $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);

        while (User::query()->where('student_code', $code)->exists()) {
            $sequence++;
            $code = $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
        }

        return $code;
    }

    public function assignIfMissing(User $student): string
    {
        if ($student->student_code) {
            return $student->student_code;
        }

        $code = $this->generate();
        $student->forceFill(['student_code' => $code])->save();

        return $code;
    }
}
