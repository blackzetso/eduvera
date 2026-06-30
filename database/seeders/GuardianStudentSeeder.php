<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Database\Seeders\Concerns\SeedsEgyptianIdentity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class GuardianStudentSeeder extends Seeder
{
  use SeedsEgyptianIdentity;

  public const TOTAL_GUARDIANS = 50;

  public const TOTAL_STUDENTS = 100;

  public function run(): void
  {
    $leafCategories = Category::query()
      ->whereDoesntHave('children')
      ->get();

    if ($leafCategories->isEmpty()) {
      $this->command?->warn('No leaf categories found. Run CategorySeeder first.');

      return;
    }

    DB::transaction(function () use ($leafCategories) {
      $guardians = $this->seedGuardians();
      $students = $this->seedStudents($leafCategories);
      $this->linkGuardiansToStudents($guardians, $students);
    });
  }

  protected function seedGuardians(): array
  {
    $guardians = [];

    $guardians[] = User::create([
      'name' => 'محمد أحمد حسن',
      'email' => 'guardian.demo@eduvera.test',
      'password' => Hash::make(self::DEMO_PASSWORD),
      'user_type' => 'guardian',
      'phone' => '01012345678',
      'national_id' => self::DEMO_GUARDIAN_NATIONAL_ID,
      'email_verified_at' => now(),
    ]);

    for ($i = 1; $i < self::TOTAL_GUARDIANS; $i++) {
      $gender = $i % 3 === 0 ? 'female' : 'male';
      $names = $this->randomEgyptianName($gender, $i);
      $guardians[] = User::create([
        'name' => $names['name'],
        'email' => 'guardian' . str_pad((string) $i, 3, '0', STR_PAD_LEFT) . '@eduvera.test',
        'password' => Hash::make(self::DEMO_PASSWORD),
        'user_type' => 'guardian',
        'phone' => $this->egyptianPhone($i),
        'national_id' => $this->uniqueNationalId($i, 1970 + ($i % 20)),
        'email_verified_at' => now(),
      ]);
    }

    return $guardians;
  }

  protected function seedStudents($leafCategories): array
  {
    $students = [];
    $perCategory = (int) ceil(self::TOTAL_STUDENTS / max(1, $leafCategories->count()));
    $studentNumber = 1;

    foreach ($leafCategories as $category) {
      $count = min($perCategory, self::TOTAL_STUDENTS - count($students));
      if ($count <= 0) {
        break;
      }

      for ($j = 0; $j < $count; $j++) {
        $gender = ($studentNumber + $j) % 2 === 0 ? 'male' : 'female';
        $names = $this->randomEgyptianName($gender, $studentNumber);
        $dob = $this->studentBirthDate($studentNumber, $category->name);

        $email = null;
        if ($studentNumber === 1) {
          $email = 'student.warning@eduvera.test';
        } elseif ($studentNumber === 2) {
          $email = 'student.critical@eduvera.test';
        } else {
          $email = 'student' . str_pad((string) $studentNumber, 3, '0', STR_PAD_LEFT) . '@eduvera.test';
        }

        $students[] = User::create([
          'name' => $names['name'],
          'first_name' => $names['first_name'],
          'father_name' => $names['father_name'],
          'grandfather_name' => $names['grandfather_name'],
          'email' => $email,
          'password' => Hash::make(self::DEMO_PASSWORD),
          'user_type' => 'student',
          'phone' => $this->egyptianPhone(500 + $studentNumber),
          'national_id' => $this->uniqueNationalId(500 + $studentNumber, (int) substr($dob, 0, 4)),
          'student_code' => $this->studentCode($studentNumber),
          'category_id' => $category->id,
          'gender' => $gender,
          'date_of_birth' => $dob,
          'enrollment_date' => now()->subMonths(rand(1, 24))->toDateString(),
          'email_verified_at' => now(),
        ]);

        $studentNumber++;
        if (count($students) >= self::TOTAL_STUDENTS) {
          break 2;
        }
      }
    }

    return $students;
  }

  protected function linkGuardiansToStudents(array $guardians, array $students): void
  {
    $demoGuardian = $guardians[0];
    $linked = 0;

    foreach ($students as $index => $student) {
      if ($index < 5) {
        continue;
      }

      if ($index % 20 === 0) {
        continue;
      }

      if ($index % 4 === 0 && isset($guardians[$index % count($guardians)])) {
        $g1 = $guardians[$index % count($guardians)];
        $g2 = $guardians[($index + 1) % count($guardians)];
        $student->guardians()->syncWithoutDetaching([$g1->id, $g2->id]);
      } else {
        $guardian = $guardians[$index % count($guardians)];
        $student->guardians()->syncWithoutDetaching([$guardian->id]);
      }

      $linked++;
    }

    if (isset($students[0])) {
      $demoGuardian->students()->syncWithoutDetaching([$students[0]->id]);
    }
    if (isset($students[1])) {
      $demoGuardian->students()->syncWithoutDetaching([$students[1]->id]);
    }
    if (isset($students[2])) {
      $demoGuardian->students()->syncWithoutDetaching([$students[2]->id]);
    }

    $existingGuardian = User::where('email', 'guardian@example.com')->first();
    if ($existingGuardian && isset($students[0])) {
      $existingGuardian->update(['national_id' => self::DEMO_GUARDIAN_NATIONAL_ID]);
      $existingGuardian->students()->syncWithoutDetaching([$students[0]->id, $students[1]->id ?? $students[0]->id]);
    }
  }
}
