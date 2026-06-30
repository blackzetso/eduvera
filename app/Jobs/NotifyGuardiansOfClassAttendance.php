<?php

namespace App\Jobs;

use App\Models\StudentAttendance;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyGuardiansOfClassAttendance implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $periodId,
        public string $date,
    ) {}

    public function handle(WhatsAppService $whatsApp): void
    {
        $absences = StudentAttendance::query()
            ->where('timetable_period_id', $this->periodId)
            ->whereDate('attendance_date', $this->date)
            ->whereIn('status', ['absent', 'late'])
            ->with('student.guardians')
            ->get();

        foreach ($absences as $record) {
            $student = $record->student;
            if (! $student) {
                continue;
            }

            $statusLabel = $record->status === 'absent' ? 'غائب' : 'متأخر';
            $message = "إشعار حضور: الطالب {$student->name} {$statusLabel} بتاريخ {$this->date}.";

            foreach ($student->guardians as $guardian) {
                if ($guardian->phone) {
                    $whatsApp->sendMessage($guardian->phone, $message);
                }
            }
        }
    }
}
