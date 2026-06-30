<?php

namespace App\Support\Student;

use Carbon\Carbon;

class AcademicYear
{
    public static function forDate(?Carbon $date = null): string
    {
        $date ??= now();
        $startMonth = (int) config('student.academic_year_start_month', 9);

        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');

        if ($month >= $startMonth) {
            return $year . '-' . ($year + 1);
        }

        return ($year - 1) . '-' . $year;
    }
}
