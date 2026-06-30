<?php

namespace App\Modules\Canteen\Services;

use App\Modules\Canteen\Models\Category;
use App\Modules\Canteen\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductService
{
    public function __construct(protected AuditService $audit) {}

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return $this->query($filters)->with('category:id,name,name_ar,slug')->paginate($filters['per_page'] ?? 15);
    }

    public function listActiveCatalog(array $filters = []): Collection
    {
        return $this->query(['is_active' => true] + $filters)
            ->with('category:id,name,name_ar,slug,is_active')
            ->orderBy('name')
            ->get();
    }

    public function find(string $id): Product
    {
        return Product::query()->with('category:id,name,name_ar,slug')->findOrFail($id);
    }

    public function findByBarcode(string $barcode): ?Product
    {
        return Product::query()->with('category:id,name,name_ar,slug')->where('barcode', $barcode)->first();
    }

    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $this->assertCategory($data['category_id']);
            $product = Product::query()->create($this->pick($data));
            $this->audit->log('product.created', $product, after: $product->fresh()->toArray());

            return $product->fresh()->load('category:id,name,name_ar,slug');
        });
    }

    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            if (isset($data['category_id'])) {
                $this->assertCategory($data['category_id']);
            }
            $before = $product->toArray();
            $product->update($this->pick($data));
            $this->audit->log('product.updated', $product, before: $before, after: $product->fresh()->toArray());

            return $product->fresh()->load('category:id,name,name_ar,slug');
        });
    }

    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $before = $product->toArray();
            $product->delete();
            $this->audit->log('product.deleted', $product, before: $before);
        });
    }

    protected function query(array $filters = [])
    {
        $q = Product::query()->orderBy('name');

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $q->where('is_active', (bool) $filters['is_active']);
        }
        if (! empty($filters['category_id'])) {
            $q->where('category_id', $filters['category_id']);
        }
        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $q->where(fn ($b) => $b
                ->where('name', 'like', "%{$s}%")
                ->orWhere('sku', 'like', "%{$s}%")
                ->orWhere('barcode', 'like', "%{$s}%"));
        }

        return $q;
    }

    protected function pick(array $data): array
    {
        return collect($data)->only([
            'category_id', 'sku', 'barcode', 'name', 'name_ar', 'description', 'unit',
            'selling_price', 'cost_price', 'is_active', 'is_restricted_default',
            'restriction_tags', 'image_path', 'metadata',
        ])->toArray();
    }

    protected function assertCategory(string $categoryId): void
    {
        if (! Category::query()->whereKey($categoryId)->where('is_active', true)->exists()) {
            throw new InvalidArgumentException('The selected category is invalid or inactive.');
        }
    }
}
