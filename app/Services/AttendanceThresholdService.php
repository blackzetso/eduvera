<?php

namespace App\Services;

use App\Models\AttendanceAlert;
use App\Models\AttendanceThreshold;
use App\Models\StudentAttendance;
use App\Models\User;
use App\Notifications\StudentAbsenceNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AttendanceThresholdService
{
    public function __construct(
        protected WhatsAppService $whatsApp,
    ) {}

    public function resolveThreshold(?int $categoryId): AttendanceThreshold
    {
        if ($categoryId) {
            $specific = AttendanceThreshold::query()
                ->where('is_active', true)
                ->where('category_id', $categoryId)
                ->first();

            if ($specific) {
                return $specific;
            }
        }

        return AttendanceThreshold::query()
            ->where('is_active', true)
            ->whereNull('category_id')
            ->firstOrFail();
    }

    public function countAbsencesForStudent(int $studentId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->startOfYear();
        $to = $to ?? now();

        $query = StudentAttendance::query()
            ->where('student_id', $studentId)
            ->excludingLiveStreamForThresholds()
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()]);

        return [
            'absences' => (clone $query)->where('status', 'absent')->count(),
            'late' => (clone $query)->where('status', 'late')->count(),
        ];
    }

    public function evaluateStudent(User $student): ?AttendanceAlert
    {
        if ($student->user_type !== 'student') {
            return null;
        }

        $threshold = $this->resolveThreshold($student->category_id);
        $counts = $this->countAbsencesForStudent($student->id);

        $level = null;
        if ($counts['absences'] >= $threshold->critical_absences) {
            $level = 'critical';
        } elseif ($counts['absences'] >= $threshold->warning_absences) {
            $level = 'warning';
        }

        if (! $level) {
            return null;
        }

        $existing = AttendanceAlert::query()
            ->where('student_id', $student->id)
            ->where('level', $level)
            ->whereNull('acknowledged_at')
            ->where('triggered_at', '>=', now()->subDays(7))
            ->first();

        if ($existing) {
            return $existing;
        }

        $alert = AttendanceAlert::create([
            'student_id' => $student->id,
            'academic_year' => $threshold->academic_year,
            'period_label' => now()->year.'-'.(now()->year + 1),
            'level' => $level,
            'absences_count' => $counts['absences'],
            'late_count' => $counts['late'],
            'triggered_at' => now(),
            'action_taken' => $level === 'critical' && $threshold->suggest_block_at_critical
                ? 'warning_sent'
                : 'none',
        ]);

        if ($threshold->auto_notify_guardian) {
            $this->notifyGuardians($student, $alert);
        }

        return $alert;
    }

    public function checkAllStudents(): int
    {
        $created = 0;

        User::query()
            ->where('user_type', 'student')
            ->chunkById(100, function ($students) use (&$created) {
                foreach ($students as $student) {
                    try {
                        if ($this->evaluateStudent($student)) {
                            $created++;
                        }
                    } catch (\Throwable $e) {
                        Log::error('Attendance threshold check failed', [
                            'student_id' => $student->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $created;
    }

    protected function notifyGuardians(User $student, AttendanceAlert $alert): void
    {
        $message = $alert->level === 'critical'
            ? "تنبيه: الطالب {$student->name} تجاوز حد الغياب الحرج ({$alert->absences_count} غياب)."
            : "تنبيه: الطالب {$student->name} اقترب من حد الغياب ({$alert->absences_count} غياب).";

        foreach ($student->guardians as $guardian) {
            if ($guardian->phone) {
                $this->whatsApp->sendMessage($guardian->phone, $message);
            }

            $guardian->notify(new StudentAbsenceNotification($student, $alert));
        }
    }
}
