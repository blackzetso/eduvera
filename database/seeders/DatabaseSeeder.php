<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Demo test accounts (password for all: 12345678)
 *
 * | Role          | Email                          | Notes                              |
 * |---------------|--------------------------------|------------------------------------|
 * | Admin         | admin@example.com              |                                    |
 * | Guardian      | guardian.demo@eduvera.test     | national_id: 29309208800736 — /guardian/dashboard |
 * | Guardian      | guardian@example.com           | same national_id, linked in seeder |
 * | Teacher       | teacher.demo@eduvera.test      | has timetable assignments          |
 * | Student       | student.warning@eduvera.test   | warning-level absences             |
 * | Student       | student.critical@eduvera.test  | critical-level absences            |
 * | Control staff | control@eduvera.test           |                                    |
 */
class DatabaseSeeder extends Seeder
{
  public function run(): void
  {
    $this->call([
      AdminSeeder::class,
      LanguageSeeder::class,
      CategorySeeder::class,
      SubjectSeeder::class,
      StaffSeeder::class,
      GuardianStudentSeeder::class,
      TimetableSeeder::class,
      LessonSeeder::class,
      AttendanceThresholdSeeder::class,
      AttendanceSystemTaskSeeder::class,
      AttendanceDemoSeeder::class,
      TeacherAbsenceDemoSeeder::class,
      GuardianPortalSeeder::class,
      FormTemplateSeeder::class,
    ]);
  }
}
