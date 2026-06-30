<?php

namespace App\Services\Dova;

use App\Models\DovaFaqCategory;
use Illuminate\Support\Str;

class DovaFaqCategoryService
{
    public function ensureDefaults(): void
    {
        foreach (config('dova-knowledge.faq_categories', []) as $index => $cat) {
            DovaFaqCategory::query()->firstOrCreate(
                ['slug' => $cat['slug']],
                [
                    'name_en' => $cat['name_en'],
                    'name_ar' => $cat['name_ar'],
                    'is_system' => true,
                    'sort_order' => $index,
                ],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForSelect(): array
    {
        $this->ensureDefaults();

        return DovaFaqCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get()
            ->map(fn (DovaFaqCategory $c) => [
                'id' => $c->id,
                'slug' => $c->slug,
                'name' => $c->name_ar,
                'nameEn' => $c->name_en,
            ])
            ->all();
    }

    public function findOrCreateCustom(string $name): DovaFaqCategory
    {
        $slug = Str::slug($name) ?: 'custom-'.time();

        return DovaFaqCategory::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name_en' => $name,
                'name_ar' => $name,
                'is_system' => false,
                'sort_order' => 999,
            ],
        );
    }
}
