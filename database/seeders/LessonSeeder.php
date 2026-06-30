<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Lecture;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\TimetableAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LessonSeeder extends Seeder
{
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
    $leafCategories = Category::query()->whereDoesntHave('children')->get();

    if ($teachers->isEmpty() || $leafCategories->isEmpty()) {
      return;
    }

    DB::transaction(function () use ($subjects, $teachers, $leafCategories) {
      $lectureTitles = ['المحاضرة 1', 'المحاضرة 2', 'مراجعة', 'تمارين', 'اختبار قصير'];

      foreach ($leafCategories as $catIndex => $category) {
        foreach ($this->coreSubjects as $subIndex => $subjectName) {
          $subject = $subjects->get($subjectName);
          if (! $subject) {
            continue;
          }

          $teacher = $teachers[($catIndex + $subIndex) % $teachers->count()];

          $lesson = Lesson::create([
            'name' => $subjectName . ' - ' . $category->name,
            'short_description' => 'منهج ' . $subjectName . ' للصف ' . $category->name,
            'description' => 'محتوى تعليمي شامل لمادة ' . $subjectName . ' وفق المنهج المصري.',
            'category_id' => $category->id,
            'teacher_id' => $teacher->id,
            'status' => 'enable',
            'is_free' => true,
          ]);

          foreach (array_slice($lectureTitles, 0, 3 + ($subIndex % 3)) as $lectureName) {
            Lecture::create([
              'lesson_id' => $lesson->id,
              'name' => $lectureName,
            ]);
          }

          $assignment = TimetableAssignment::query()
            ->whereHas('period', fn ($q) => $q->where('category_id', $category->id))
            ->where('subject_id', $subject->id)
            ->where('type', 'main')
            ->with('period')
            ->first();

          if ($assignment?->period) {
            $lesson->timetablePeriods()->syncWithoutDetaching([$assignment->period->id]);
          }
        }
      }
    });
  }
}
