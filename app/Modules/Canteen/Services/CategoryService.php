<?php

namespace App\Modules\Canteen\Services;

use App\Modules\Canteen\Models\Category;
use App\Modules\Canteen\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CategoryService
{
    public function __construct(protected AuditService $audit) {}

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = Category::query()->withCount('products')->orderBy('sort_order')->orderBy('name');

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(fn ($b) => $b
                ->where('name', 'like', "%{$s}%")
                ->orWhere('name_ar', 'like', "%{$s}%")
                ->orWhere('slug', 'like', "%{$s}%"));
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function allActive(): Collection
    {
        return Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    public function find(string $id): Category
    {
        return Category::query()->withCount('products')->findOrFail($id);
    }

    public function create(array $data): Category
    {
        return DB::transaction(function () use ($data) {
            $category = Category::query()->create($this->prepare($data));
            $this->audit->log('category.created', $category, after: $category->fresh()->toArray());

            return $category->fresh()->loadCount('products');
        });
    }

    public function update(Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data) {
            $before = $category->toArray();
            $category->update($this->prepare($data, $category));
            $this->audit->log('category.updated', $category, before: $before, after: $category->fresh()->toArray());

            return $category->fresh()->loadCount('products');
        });
    }

    public function delete(Category $category): void
    {
        DB::transaction(function () use ($category) {
            if (Product::query()->where('category_id', $category->id)->where('is_active', true)->exists()) {
                throw new InvalidArgumentException('Cannot delete a category with active products.');
            }
            $before = $category->toArray();
            $category->delete();
            $this->audit->log('category.deleted', $category, before: $before);
        });
    }

    protected function prepare(array $data, ?Category $existing = null): array
    {
        $payload = collect($data)->only([
            'name', 'name_ar', 'slug', 'description', 'sort_order', 'is_active', 'metadata',
        ])->toArray();

        if (empty($payload['slug'])) {
            $base = Str::slug($payload['name'] ?? $existing?->name ?? 'category');
            $slug = $base;
            $i = 1;
            while (Category::withTrashed()->where('slug', $slug)->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))->exists()) {
                $slug = $base.'-'.$i;
                $i++;
            }
            $payload['slug'] = $slug;
        }

        return $payload;
    }
}
