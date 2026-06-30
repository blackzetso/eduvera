<?php

namespace App\Jobs;

use App\Models\AttendanceImportBatch;
use App\Models\StudentAttendance;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyGuardiansOfBulkAttendance implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $batchId,
    ) {}

    public function handle(WhatsAppService $whatsApp): void
    {
        $batch = AttendanceImportBatch::find($this->batchId);
        if (! $batch) {
            return;
        }

        $records = StudentAttendance::query()
            ->where('import_batch_id', $batch->id)
            ->whereIn('status', ['absent', 'late'])
            ->with('student.guardians')
            ->get();

        foreach ($records as $record) {
            $student = $record->student;
            if (! $student) {
                continue;
            }

            $message = "إشعار حضور جماعي: الطالب {$student->name} — {$record->status} بتاريخ {$record->attendance_date->format('Y-m-d')}.";

            foreach ($student->guardians as $guardian) {
                if ($guardian->phone) {
                    $whatsApp->sendMessage($guardian->phone, $message);
                }
            }
        }
    }
}
