<?php

namespace App\Services;

use App\Models\Category;
use App\Models\StudentAttendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AttendanceStatsService
{
    public function todayKpis(?int $categoryId = null): array
    {
        $cacheKey = 'attendance_kpis_'.today()->toDateString().'_'.($categoryId ?? 'all');

        return Cache::remember($cacheKey, 60, function () use ($categoryId) {
            $date = today()->toDateString();
            $query = StudentAttendance::query()
                ->whereDate('attendance_date', $date)
                ->where('session_type', '!=', 'live_stream');

            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            $total = (clone $query)->count();
            $present = (clone $query)->where('status', 'present')->count();
            $absent = (clone $query)->where('status', 'absent')->count();
            $late = (clone $query)->where('status', 'late')->count();
            $excused = (clone $query)->where('status', 'excused')->count();

            $studentQuery = User::query()->where('user_type', 'student');
            if ($categoryId) {
                $studentQuery->where('category_id', $categoryId);
            }
            $totalStudents = $studentQuery->count();

            return [
                'date' => $date,
                'total_records' => $total,
                'present' => $present,
                'absent' => $absent,
                'late' => $late,
                'excused' => $excused,
                'total_students' => $totalStudents,
                'attendance_rate' => $totalStudents > 0
                    ? round(($present + $late) / max($total, 1) * 100, 1)
                    : 0,
            ];
        });
    }

    public function studentSummary(int $studentId, ?Carbon $from = null, ?Carbon $to = null, int $recordsLimit = 50): array
    {
        $from = $from ?? now()->startOfYear();
        $to = $to ?? now();

        $base = StudentAttendance::query()
            ->where('student_id', $studentId)
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
            ->where('session_type', '!=', 'live_stream');

        $records = StudentAttendance::query()
            ->where('student_id', $studentId)
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('attendance_date')
            ->limit($recordsLimit)
            ->get();

        $totalRecords = (clone $base)->count();

        return [
            'total' => $totalRecords,
            'present' => (clone $base)->where('status', 'present')->count(),
            'absent' => (clone $base)->where('status', 'absent')->count(),
            'late' => (clone $base)->where('status', 'late')->count(),
            'excused' => (clone $base)->where('status', 'excused')->count(),
            'records_limited' => $totalRecords > $recordsLimit,
            'records' => $records->values(),
        ];
    }

    public function invalidateTodayCache(?int $categoryId = null): void
    {
        Cache::forget('attendance_kpis_'.today()->toDateString().'_'.($categoryId ?? 'all'));
    }
}
