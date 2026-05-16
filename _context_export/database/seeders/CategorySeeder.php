<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // إعدادي
        $preparatory = Category::create([
            'name' => 'إعدادي',
            'parent_id' => null,
        ]);

        // إعدادي - الفروع
        Category::create([
            'name' => 'أولى إعدادي',
            'parent_id' => $preparatory->id,
        ]);

        Category::create([
            'name' => 'تانية إعدادي',
            'parent_id' => $preparatory->id,
        ]);

        Category::create([
            'name' => 'تالتة إعدادي',
            'parent_id' => $preparatory->id,
        ]);

        // ثانوي
        $secondary = Category::create([
            'name' => 'ثانوي',
            'parent_id' => null,
        ]);

        // ثانوي - الفروع
        Category::create([
            'name' => 'أولى ثانوي',
            'parent_id' => $secondary->id,
        ]);

        Category::create([
            'name' => 'تانية ثانوي',
            'parent_id' => $secondary->id,
        ]);

        Category::create([
            'name' => 'تالتة ثانوي',
            'parent_id' => $secondary->id,
        ]);
    }
}
