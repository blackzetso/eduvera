<?php

namespace Database\Seeders;

use App\Models\AttendanceAlert;
use App\Models\AttendanceAuditLog;
use App\Models\AttendanceCardReader;
use App\Models\GuardianNotificationPreference;
use App\Models\StudentAttendance;
use App\Models\TimetablePeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AttendanceDemoSeeder extends Seeder
{
  protected array $sources = ['manual', 'manual', 'manual', 'card', 'excel', 'mobile_app'];

  public function run(): void
  {
    $this->seedCardReaders();
    $this->seedGuardianNotificationPreferences();
    $this->seedAttendanceRecords();
    $this->seedAlerts();
    $this->seedAuditLogs();
  }

  protected function seedCardReaders(): void
  {
    AttendanceCardReader::firstOrCreate(
      ['device_id' => 'GATE-MAIN-001'],
      [
        'name' => 'بوابة المدرسة الرئيسية',
        'location' => 'المدخل الرئيسي',
        'api_key_hash' => Hash::make('demo-card-api-key-main'),
        'session_type' => 'morning',
        'default_status' => 'present',
        'late_after_time' => '08:15:00',
        'is_active' => true,
        'last_seen_at' => now()->subHours(2),
      ]
    );

    AttendanceCardReader::firstOrCreate(
      ['device_id' => 'GATE-SIDE-002'],
      [
        'name' => 'المدخل الجانبي',
        'location' => 'الجناح الشرقي',
        'api_key_hash' => Hash::make('demo-card-api-key-side'),
        'session_type' => 'morning',
        'default_status' => 'late',
        'late_after_time' => '08:30:00',
        'is_active' => true,
        'last_seen_at' => now()->subDay(),
      ]
    );
  }

  protected function seedGuardianNotificationPreferences(): void
  {
    $pairs = DB::table('guardian_student')->get();

    foreach ($pairs as $pair) {
      GuardianNotificationPreference::firstOrCreate(
        [
          'guardian_id' => $pair->guardian_id,
          'student_id' => $pair->student_id,
        ],
        [
          'notify_absence' => true,
          'notify_late' => true,
          'notify_whatsapp' => true,
          'notify_email' => false,
          'notify_in_app' => true,
        ]
      );
    }
  }

  protected function seedAttendanceRecords(): void
  {
    $schoolDays = $this->lastSchoolDays(30);
    $students = User::query()->ofType('student')->with('category')->get();
    $periodsByCategory = TimetablePeriod::query()
      ->with(['assignments' => fn ($q) => $q->where('type', 'main')])
      ->get()
      ->groupBy('category_id');

    $admin = User::where('user_type', 'admin')->first();
    $recordedBy = $admin?->id;

    $warningEmails = ['student.warning@eduvera.test'];
    $criticalEmails = ['student.critical@eduvera.test'];
    $extraWarningIds = $students->slice(10, 4)->pluck('id')->all();
    $extraCriticalIds = $students->slice(14, 2)->pluck('id')->all();

    $rows = [];
    $now = now();

    foreach ($students as $student) {
      $periods = $periodsByCategory->get($student->category_id, collect());
      if ($periods->isEmpty()) {
        continue;
      }

      $targetAbsences = 0;
      if (in_array($student->email, $warningEmails, true) || in_array($student->id, $extraWarningIds, true)) {
        $targetAbsences = rand(6, 8);
      } elseif (in_array($student->email, $criticalEmails, true) || in_array($student->id, $extraCriticalIds, true)) {
        $targetAbsences = rand(11, 14);
      }

      $absenceCount = 0;
      $dayIndex = 0;

      foreach ($schoolDays as $date) {
        $periodSample = $periods->take(4);
        foreach ($periodSample as $period) {
          $assignment = $period->assignments->first();
          $subjectId = $assignment?->subject_id;

          $status = $this->randomStatus();

          if ($targetAbsences > 0 && $absenceCount < $targetAbsences && ($dayIndex + $period->period_number) % 3 === 0) {
            $status = 'absent';
            $absenceCount++;
          }

          $rows[] = [
            'student_id' => $student->id,
            'category_id' => $student->category_id,
            'attendance_date' => $date,
            'session_type' => 'class',
            'session_label' => null,
            'timetable_period_id' => $period->id,
            'subject_id' => $subjectId,
            'period_number' => $period->period_number,
            'live_stream_id' => null,
            'status' => $status,
            'arrival_time' => $status === 'late' ? '08:20:00' : null,
            'minutes_late' => $status === 'late' ? rand(5, 25) : null,
            'excused_reason' => $status === 'excused' ? 'عذر طبي' : null,
            'notes' => null,
            'source' => $this->sources[array_rand($this->sources)],
            'recorded_by' => $recordedBy,
            'card_reader_id' => null,
            'import_batch_id' => null,
            'metadata_json' => null,
            'created_at' => $now,
            'updated_at' => $now,
          ];
        }
        $dayIndex++;
      }
    }

    foreach (array_chunk($rows, 500) as $chunk) {
      StudentAttendance::insertOrIgnore($chunk);
    }
  }

  protected function seedAlerts(): void
  {
    $ordered = User::query()->ofType('student')->orderBy('id')->get();
    $warningIds = $ordered->slice(10, 4)->pluck('id')->push(
      User::where('email', 'student.warning@eduvera.test')->value('id')
    )->filter()->unique();

    $criticalIds = $ordered->slice(14, 2)->pluck('id')->push(
      User::where('email', 'student.critical@eduvera.test')->value('id')
    )->filter()->unique();

    $warningStudents = User::whereIn('id', $warningIds)->get();
    $criticalStudents = User::whereIn('id', $criticalIds)->get();

    foreach ($warningStudents as $student) {
      $counts = $this->countAbsences($student->id);
      if ($counts['absences'] >= 5) {
        AttendanceAlert::firstOrCreate(
          [
            'student_id' => $student->id,
            'level' => 'warning',
            'acknowledged_at' => null,
          ],
          [
            'academic_year' => '2025-2026',
            'period_label' => 'الفصل الدراسي الأول',
            'absences_count' => $counts['absences'],
            'late_count' => $counts['late'],
            'triggered_at' => now()->subDays(2),
            'action_taken' => 'warning_sent',
          ]
        );
      }
    }

    foreach ($criticalStudents as $student) {
      $counts = $this->countAbsences($student->id);
      if ($counts['absences'] >= 10) {
        AttendanceAlert::firstOrCreate(
          [
            'student_id' => $student->id,
            'level' => 'critical',
            'acknowledged_at' => null,
          ],
          [
            'academic_year' => '2025-2026',
            'period_label' => 'الفصل الدراسي الأول',
            'absences_count' => $counts['absences'],
            'late_count' => $counts['late'],
            'triggered_at' => now()->subDay(),
            'action_taken' => 'none',
          ]
        );
      }
    }
  }

  protected function seedAuditLogs(): void
  {
    $sample = StudentAttendance::query()
      ->where('status', 'absent')
      ->inRandomOrder()
      ->limit(50)
      ->get();

    $admin = User::where('user_type', 'admin')->first();

    foreach ($sample as $attendance) {
      AttendanceAuditLog::create([
        'attendance_id' => $attendance->id,
        'actor_id' => $admin?->id,
        'event' => 'created',
        'old_values_json' => null,
        'new_values_json' => ['status' => $attendance->status],
        'reason' => 'تسجيل تلقائي من السيدر',
        'ip_address' => '127.0.0.1',
        'created_at' => $attendance->created_at ?? now(),
      ]);
    }
  }

  protected function countAbsences(int $studentId): array
  {
    $from = now()->startOfYear()->toDateString();
    $to = now()->toDateString();

    return [
      'absences' => StudentAttendance::query()
        ->where('student_id', $studentId)
        ->where('session_type', '!=', 'live_stream')
        ->whereBetween('attendance_date', [$from, $to])
        ->where('status', 'absent')
        ->count(),
      'late' => StudentAttendance::query()
        ->where('student_id', $studentId)
        ->where('session_type', '!=', 'live_stream')
        ->whereBetween('attendance_date', [$from, $to])
        ->where('status', 'late')
        ->count(),
    ];
  }

  protected function lastSchoolDays(int $count): array
  {
    $days = [];
    $date = Carbon::today();

    while (count($days) < $count) {
      if (! $date->isFriday() && ! $date->isSaturday()) {
        $days[] = $date->toDateString();
      }
      $date->subDay();
    }

    return array_reverse($days);
  }

  protected function randomStatus(): string
  {
    $roll = rand(1, 100);
    if ($roll <= 80) {
      return 'present';
    }
    if ($roll <= 92) {
      return 'absent';
    }
    if ($roll <= 97) {
      return 'late';
    }

    return 'excused';
  }
}
