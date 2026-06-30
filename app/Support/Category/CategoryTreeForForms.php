<?php

namespace App\Support\Category;

use App\Models\Category;

class CategoryTreeForForms
{
    public static function build(): array
    {
        return Category::whereNull('parent_id')
            ->where('status', 'enable')
            ->with(['children' => fn ($q) => $q->where('status', 'enable')->orderBy('name')
                ->with(['children' => fn ($q2) => $q2->where('status', 'enable')->orderBy('name')
                    ->with(['children' => fn ($q3) => $q3->where('status', 'enable')->orderBy('name')]),
                ]),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'children' => $c->children->map(fn ($ch) => [
                    'id' => $ch->id,
                    'name' => $ch->name,
                    'children' => $ch->children->map(fn ($gch) => [
                        'id' => $gch->id,
                        'name' => $gch->name,
                        'children' => $gch->children->map(fn ($sgch) => [
                            'id' => $sgch->id,
                            'name' => $sgch->name,
                        ])->values(),
                    ])->values(),
                ])->values(),
            ])
            ->all();
    }
}
