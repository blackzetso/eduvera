<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Subject;
use App\Models\Timetable;
use App\Models\TimetableAssignment;
use App\Models\TimetableDay;
use App\Models\TimetablePeriod;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TimetableSeeder extends Seeder
{
  protected array $periodSlots = [
    ['period_number' => 1, 'time_from' => '08:00', 'time_to' => '08:45'],
    ['period_number' => 2, 'time_from' => '08:50', 'time_to' => '09:35'],
    ['period_number' => 3, 'time_from' => '09:45', 'time_to' => '10:30'],
    ['period_number' => 4, 'time_from' => '10:35', 'time_to' => '11:20'],
    ['period_number' => 5, 'time_from' => '11:30', 'time_to' => '12:15'],
    ['period_number' => 6, 'time_from' => '12:20', 'time_to' => '13:05'],
  ];

  protected array $dayNames = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس'];

  protected array $dayNameToLegacy = [
    'الأحد' => 'sunday',
    'الإثنين' => 'monday',
    'الثلاثاء' => 'tuesday',
    'الأربعاء' => 'wednesday',
    'الخميس' => 'thursday',
    'الجمعة' => 'friday',
    'السبت' => 'saturday',
  ];

  protected array $coreSubjects = [
    'اللغة العربية',
    'الرياضيات',
    'اللغة الإنجليزية',
    'العلوم',
    'الدراسات الاجتماعية',
    'التربية الدينية',
  ];

  public function run(): void
  {
    $subjects = Subject::all()->keyBy('name');
    $teachers = User::query()->ofType('teacher')->get();
    $admin = User::where('user_type', 'admin')->first();
    $leafCategories = Category::query()->whereDoesntHave('children')->get();

    if ($teachers->isEmpty() || $leafCategories->isEmpty()) {
      return;
    }

    DB::transaction(function () {
      TimetableAssignment::query()->delete();
      TimetablePeriod::query()->delete();
      TimetableDay::query()->delete();
      Timetable::query()->delete();
    });

    DB::transaction(function () use ($subjects, $teachers, $admin, $leafCategories) {
      $timetable = Timetable::create([
        'name' => 'الجدول الدراسي',
        'academic_year' => '2025-2026',
        'status' => 'active',
      ]);

      $days = [];
      foreach ($this->dayNames as $order => $dayName) {
        $days[] = TimetableDay::create([
          'timetable_id' => $timetable->id,
          'day_name' => $dayName,
          'day_order' => $order + 1,
          'is_active' => true,
        ]);
      }

      $subjectList = collect($this->coreSubjects)
        ->map(fn ($name) => $subjects->get($name))
        ->filter()
        ->values();

      $demoTeacher = User::where('email', 'teacher@example.com')->first();
      $leafCount = $leafCategories->count();

      foreach ($leafCategories as $catIndex => $category) {
        $category->subjects()->syncWithoutDetaching($subjectList->pluck('id')->all());

        foreach ($days as $day) {
          foreach ($this->periodSlots as $slotIndex => $slot) {
            $periodData = [
              'timetable_id' => $timetable->id,
              'timetable_day_id' => $day->id,
              'period_number' => $slot['period_number'],
              'time_from' => $slot['time_from'],
              'time_to' => $slot['time_to'],
              'category_id' => $category->id,
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('timetable_periods', 'day')) {
              $periodData['day'] = $this->dayNameToLegacy[$day->day_name] ?? 'sunday';
            }

            $period = TimetablePeriod::create($periodData);

            // Sunday periods 5 & 6 (11:30–12:15, 12:20–13:05): leave empty for demo teacher self-assign
            if ($day->day_name === 'الأحد' && $slotIndex >= 4) {
              continue;
            }

            // Leave 2 empty slots per weekday for demo teacher self-assign (periods 5 & 6)
            $selfAssignCategoryIndex = ($leafCount > 0)
              ? (((int) $day->day_order - 1) % $leafCount)
              : -1;
            $isSelfAssignDemoSlot = $demoTeacher
              && $catIndex === $selfAssignCategoryIndex
              && $slotIndex >= 4;

            if ($isSelfAssignDemoSlot) {
              continue;
            }

            // Rotate teacher/subject by day so each weekday shows a different schedule
            $dayOffset = (int) $day->day_order - 1;
            $pickIndex = ($catIndex + $slotIndex + $dayOffset) % max($subjectList->count(), 1);
            $subject = $subjectList[$pickIndex % $subjectList->count()];
            $teacher = $teachers[($catIndex + $slotIndex + $dayOffset) % $teachers->count()];

            TimetableAssignment::create([
              'timetable_period_id' => $period->id,
              'teacher_id' => $teacher->id,
              'subject_id' => $subject->id,
              'assigned_by' => $admin?->id,
              'status' => 'approved',
              'type' => 'main',
            ]);

            if ($slotIndex === 0 && $day->day_order === 1) {
              $backupTeacher = $teachers[($catIndex + $slotIndex + 1) % $teachers->count()];
              TimetableAssignment::create([
                'timetable_period_id' => $period->id,
                'teacher_id' => $backupTeacher->id,
                'subject_id' => $subject->id,
                'assigned_by' => $admin?->id,
                'status' => 'approved',
                'type' => 'backup',
              ]);
            }
          }
        }
      }

      // Sync subject_user from timetable assignments (covers legacy teacher accounts)
      TimetableAssignment::query()
        ->whereNotNull('subject_id')
        ->get()
        ->groupBy('teacher_id')
        ->each(function ($assignments, $teacherId) {
          $teacher = User::find($teacherId);
          if ($teacher) {
            $teacher->subjects()->syncWithoutDetaching(
              $assignments->pluck('subject_id')->unique()->all()
            );
          }
        });
    });

    // Ensure demo teacher can self-assign on empty slots (subjects linked to categories)
    if ($demoTeacher = User::where('email', 'teacher@example.com')->first()) {
      $arabic = $subjects->get('اللغة العربية');
      $religion = $subjects->get('التربية الدينية');
      $subjectIds = collect([$arabic?->id, $religion?->id])->filter()->all();
      if ($subjectIds) {
        $demoTeacher->subjects()->syncWithoutDetaching($subjectIds);
      }
    }
  }
}
