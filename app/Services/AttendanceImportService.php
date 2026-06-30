<?php

namespace App\Services;

use App\Models\AttendanceImportBatch;
use App\Models\StudentAttendance;
use App\Jobs\NotifyGuardiansOfBulkAttendance;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AttendanceImportService
{
    public function __construct(
        protected AttendanceImportValidator $validator,
        protected AttendanceAuditService $auditService,
        protected AttendanceThresholdService $thresholdService,
    ) {}

    public function processUpload(
        UploadedFile $file,
        int $importedBy,
        string $scopeType = 'school',
        ?int $scopeId = null,
        ?string $defaultDate = null,
        ?string $sessionType = 'class',
    ): AttendanceImportBatch {
        $path = $file->store('attendance-imports', 'local');
        $extension = $file->getClientOriginalExtension() ?: 'csv';
        $fullPath = Storage::disk('local')->path($path);

        $rows = $this->validator->parseFile($fullPath, $extension);
        $result = $this->validator->validateRows($rows, $defaultDate, $sessionType);

        $status = count($result['errors']) > 0 ? 'pending_validation' : 'validated';

        return AttendanceImportBatch::create([
            'file_path' => $path,
            'original_file_name' => $file->getClientOriginalName(),
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'attendance_date' => $defaultDate,
            'session_type' => $sessionType,
            'total_rows' => count($rows),
            'success_rows' => count($result['valid']),
            'error_rows' => count($result['errors']),
            'status' => $status,
            'validation_errors_json' => $result['errors'],
            'parsed_data_json' => $result['valid'],
            'imported_by' => $importedBy,
        ]);
    }

    public function confirmImport(AttendanceImportBatch $batch): int
    {
        if ($batch->status !== 'validated') {
            throw new \RuntimeException('لا يمكن تطبيق ملف به أخطاء تحقق.');
        }

        $count = 0;

        DB::transaction(function () use ($batch, &$count) {
            $batch->update(['status' => 'importing']);

            foreach ($batch->parsed_data_json ?? [] as $row) {
                $attendance = StudentAttendance::updateOrCreate(
                    [
                        'student_id' => $row['student_id'],
                        'attendance_date' => $row['attendance_date'],
                        'session_type' => $row['session_type'],
                        'timetable_period_id' => $row['timetable_period_id'] ?? null,
                    ],
                    [
                        'category_id' => $row['category_id'],
                        'status' => $row['status'],
                        'arrival_time' => $row['arrival_time'] ?? null,
                        'notes' => $row['notes'] ?? null,
                        'source' => 'excel',
                        'recorded_by' => $batch->imported_by,
                        'import_batch_id' => $batch->id,
                    ]
                );

                if ($attendance->wasRecentlyCreated) {
                    $this->auditService->logCreated($attendance);
                }

                $count++;
            }

            $batch->update([
                'status' => 'imported',
                'imported_at' => now(),
            ]);
        });

        NotifyGuardiansOfBulkAttendance::dispatch($batch->id);
        $this->thresholdService->checkAllStudents();

        return $count;
    }
}
