<?php

namespace App\Services;

use App\Models\AttendanceCardReader;
use App\Models\StudentAttendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class AttendanceCardScanService
{
    public function __construct(
        protected AttendanceAuditService $auditService,
    ) {}

    public function authenticateReader(string $deviceId, ?string $bearerToken): AttendanceCardReader
    {
        $reader = AttendanceCardReader::query()
            ->where('device_id', $deviceId)
            ->where('is_active', true)
            ->firstOrFail();

        if (! $bearerToken || ! Hash::check($bearerToken, $reader->api_key_hash)) {
            abort(401, 'مفتاح الجهاز غير صالح');
        }

        return $reader;
    }

    public function recordScan(
        AttendanceCardReader $reader,
        string $cardCode,
        Carbon $scanTime,
        string $scanId,
    ): array {
        $existing = StudentAttendance::query()
            ->where('metadata_json->scan_id', $scanId)
            ->first();

        if ($existing) {
            return ['status' => 'duplicate', 'attendance_id' => $existing->id];
        }

        $student = User::query()
            ->where('user_type', 'student')
            ->where(function ($q) use ($cardCode) {
                $q->where('student_code', $cardCode)
                    ->orWhere('national_id', $cardCode);
            })
            ->first();

        if (! $student) {
            return ['status' => 'unknown_card'];
        }

        $status = 'present';
        if ($reader->late_after_time && $scanTime->format('H:i:s') > $reader->late_after_time) {
            $status = 'late';
        }

        $attendance = StudentAttendance::updateOrCreate(
            [
                'student_id' => $student->id,
                'attendance_date' => $scanTime->toDateString(),
                'session_type' => $reader->session_type,
                'timetable_period_id' => null,
            ],
            [
                'category_id' => $student->category_id,
                'status' => $status,
                'arrival_time' => $scanTime->format('H:i:s'),
                'source' => 'card',
                'card_reader_id' => $reader->device_id,
                'metadata_json' => [
                    'scan_id' => $scanId,
                    'device_name' => $reader->name,
                ],
            ]
        );

        if ($attendance->wasRecentlyCreated) {
            $this->auditService->logCreated($attendance);
        }

        $reader->update(['last_seen_at' => now()]);

        return ['status' => 'recorded', 'attendance_id' => $attendance->id];
    }
}
