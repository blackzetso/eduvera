<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceImportValidator
{
    public const VALID_STATUSES = ['present', 'absent', 'late', 'excused'];

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{valid: array, errors: array}
     */
    public function validateRows(array $rows, ?string $defaultDate = null, ?string $defaultSessionType = 'class'): array
    {
        $valid = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $rowErrors = [];

            $studentIdentifier = trim((string) ($row['student_code'] ?? $row['email'] ?? $row['student_email'] ?? ''));
            if ($studentIdentifier === '') {
                $rowErrors[] = 'معرّف الطالب مطلوب (student_code أو email)';
            }

            $student = null;
            if ($studentIdentifier !== '') {
                $student = User::query()
                    ->where('user_type', 'student')
                    ->where(function ($q) use ($studentIdentifier) {
                        $q->where('student_code', $studentIdentifier)
                            ->orWhere('email', $studentIdentifier);
                    })
                    ->first();

                if (! $student) {
                    $rowErrors[] = "لم يُعثر على طالب: {$studentIdentifier}";
                }
            }

            $dateStr = trim((string) ($row['attendance_date'] ?? $row['date'] ?? $defaultDate ?? ''));
            if ($dateStr === '') {
                $rowErrors[] = 'تاريخ الحضور مطلوب';
            }

            try {
                $attendanceDate = $dateStr !== '' ? Carbon::parse($dateStr)->toDateString() : null;
            } catch (\Throwable) {
                $rowErrors[] = 'تاريخ غير صالح';
                $attendanceDate = null;
            }

            $status = strtolower(trim((string) ($row['status'] ?? '')));
            if (! in_array($status, self::VALID_STATUSES, true)) {
                $rowErrors[] = 'الحالة يجب أن تكون: present, absent, late, excused';
            }

            if (! empty($rowErrors)) {
                $errors[] = ['line' => $line, 'errors' => $rowErrors, 'row' => $row];

                continue;
            }

            $valid[] = [
                'student_id' => $student->id,
                'category_id' => $student->category_id,
                'attendance_date' => $attendanceDate,
                'session_type' => $row['session_type'] ?? $defaultSessionType ?? 'class',
                'timetable_period_id' => ! empty($row['timetable_period_id']) ? (int) $row['timetable_period_id'] : null,
                'status' => $status,
                'arrival_time' => $row['arrival_time'] ?? null,
                'notes' => $row['notes'] ?? null,
            ];
        }

        return ['valid' => $valid, 'errors' => $errors];
    }

    /**
     * Parse uploaded file into rows (CSV or simple XLSX via ZipArchive).
     *
     * @return array<int, array<string, string>>
     */
    public function parseFile(string $path, string $extension): array
    {
        $extension = strtolower($extension);

        if (in_array($extension, ['csv', 'txt'], true)) {
            return $this->parseCsv($path);
        }

        if ($extension === 'xlsx') {
            return $this->parseXlsx($path);
        }

        throw new \InvalidArgumentException('صيغة الملف غير مدعومة. استخدم CSV أو XLSX.');
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function parseCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = null;
        while (($data = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $data);

                continue;
            }

            $row = [];
            foreach ($headers as $i => $key) {
                $row[$key] = $data[$i] ?? '';
            }
            if (array_filter($row)) {
                $rows[] = $row;
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Minimal XLSX reader for first sheet (shared strings + sheet1).
     *
     * @return array<int, array<string, string>>
     */
    protected function parseXlsx(string $path): array
    {
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('تعذر فتح ملف Excel');
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml) {
            $xml = simplexml_load_string($sharedXml);
            if ($xml) {
                foreach ($xml->si as $si) {
                    $sharedStrings[] = (string) ($si->t ?? $si->r->t ?? '');
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (! $sheetXml) {
            return [];
        }

        $sheet = simplexml_load_string($sheetXml);
        if (! $sheet) {
            return [];
        }

        $grid = [];
        foreach ($sheet->sheetData->row as $row) {
            $rowIndex = (int) $row['r'] - 1;
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                preg_match('/^([A-Z]+)(\d+)$/', $ref, $m);
                $col = $this->columnIndexFromLetters($m[1] ?? 'A');
                $value = '';
                if (isset($cell['t']) && (string) $cell['t'] === 's') {
                    $value = $sharedStrings[(int) $cell->v] ?? '';
                } else {
                    $value = (string) ($cell->v ?? '');
                }
                $grid[$rowIndex][$col] = $value;
            }
        }

        ksort($grid);
        $lines = array_values($grid);
        if (count($lines) < 2) {
            return [];
        }

        $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $lines[0]);
        $rows = [];
        for ($i = 1; $i < count($lines); $i++) {
            $row = [];
            foreach ($headers as $colIndex => $key) {
                $row[$key] = $lines[$i][$colIndex] ?? '';
            }
            if (array_filter($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    protected function columnIndexFromLetters(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
        }

        return $index - 1;
    }
}
