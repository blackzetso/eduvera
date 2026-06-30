<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
  public function run(): void
  {
    $subjects = [
      'اللغة العربية',
      'الرياضيات',
      'اللغة الإنجليزية',
      'العلوم',
      'الدراسات الاجتماعية',
      'التربية الدينية',
      'الحاسب الآلي',
      'التربية الرياضية',
      'التربية الفنية',
      'الفيزياء',
      'الكيمياء',
      'الأحياء',
    ];

    foreach ($subjects as $name) {
      Subject::firstOrCreate(['name' => $name]);
    }
  }
}
