<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
  public function run(): void
  {
    $preparatory = Category::create([
      'name' => 'إعدادي',
      'parent_id' => null,
    ]);

    $secondary = Category::create([
      'name' => 'ثانوي',
      'parent_id' => null,
    ]);

    $grades = [
      ['name' => 'أولى إعدادي', 'parent_id' => $preparatory->id],
      ['name' => 'تانية إعدادي', 'parent_id' => $preparatory->id],
      ['name' => 'تالتة إعدادي', 'parent_id' => $preparatory->id],
      ['name' => 'أولى ثانوي', 'parent_id' => $secondary->id],
      ['name' => 'تانية ثانوي', 'parent_id' => $secondary->id],
      ['name' => 'تالتة ثانوي', 'parent_id' => $secondary->id],
    ];

    foreach ($grades as $grade) {
      $gradeCategory = Category::create($grade);

      foreach (['أ', 'ب'] as $section) {
        Category::create([
          'name' => $grade['name'] . ' - ' . $section,
          'parent_id' => $gradeCategory->id,
        ]);
      }
    }
  }
}
