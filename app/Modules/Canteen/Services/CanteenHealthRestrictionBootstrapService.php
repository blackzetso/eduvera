<?php

namespace App\Modules\Canteen\Services;

use App\Modules\Canteen\Models\Product;
use App\Modules\Canteen\Models\StudentBlockedProduct;
use App\Modules\Canteen\Models\StudentProfile;

class CanteenHealthRestrictionBootstrapService
{
    /**
     * @return array<string, mixed>
     */
    public function defaultRestrictions(): array
    {
        return [
            'allergies' => [],
            'blocked_tags' => [],
            'block_all_purchases' => false,
            'notes' => '',
        ];
    }

    public function needsBootstrap(StudentProfile $profile): bool
    {
        $current = $profile->health_restrictions;

        if ($current === null || $current === []) {
            return true;
        }

        return $this->mergedRestrictions($current) !== $current;
    }

    public function ensureForProfile(StudentProfile $profile): bool
    {
        if (! $this->needsBootstrap($profile)) {
            return false;
        }

        $profile->update(['health_restrictions' => $this->mergedRestrictions($profile->health_restrictions)]);

        return true;
    }

    /**
     * @param  array<string, mixed>|null  $current
     * @return array<string, mixed>
     */
    protected function mergedRestrictions(?array $current): array
    {
        $defaults = $this->defaultRestrictions();
        $merged = array_merge($defaults, $current ?? []);
        $merged['allergies'] = array_values(array_unique(array_map('strval', $merged['allergies'] ?? [])));
        $merged['blocked_tags'] = array_values(array_unique(array_map('strval', $merged['blocked_tags'] ?? [])));
        $merged['block_all_purchases'] = (bool) ($merged['block_all_purchases'] ?? false);
        $merged['notes'] = (string) ($merged['notes'] ?? '');

        return $merged;
    }

    /**
     * Merge restriction tags from active guardian/admin product blocks into health_restrictions.blocked_tags.
     */
    public function needsTagPropagation(StudentProfile $profile): bool
    {
        $restrictions = $profile->health_restrictions ?? $this->defaultRestrictions();
        $existing = array_map('strval', $restrictions['blocked_tags'] ?? []);

        $productIds = StudentBlockedProduct::query()
            ->where('student_id_ref', $profile->student_id_ref)
            ->where('is_active', true)
            ->pluck('product_id');

        if ($productIds->isEmpty()) {
            return false;
        }

        $tags = Product::query()
            ->whereIn('id', $productIds)
            ->get()
            ->flatMap(fn (Product $product) => $this->productTags($product))
            ->unique()
            ->values()
            ->all();

        if ($tags === []) {
            return false;
        }

        $merged = array_values(array_unique(array_merge($existing, $tags)));

        return $merged !== $existing;
    }

    public function propagateBlockedProductTags(StudentProfile $profile): bool
    {
        if (! $this->needsTagPropagation($profile)) {
            return false;
        }

        $restrictions = $profile->health_restrictions ?? $this->defaultRestrictions();
        $existing = array_map('strval', $restrictions['blocked_tags'] ?? []);

        $productIds = StudentBlockedProduct::query()
            ->where('student_id_ref', $profile->student_id_ref)
            ->where('is_active', true)
            ->pluck('product_id');

        if ($productIds->isEmpty()) {
            return false;
        }

        $tags = Product::query()
            ->whereIn('id', $productIds)
            ->get()
            ->flatMap(fn (Product $product) => $this->productTags($product))
            ->unique()
            ->values()
            ->all();

        if ($tags === []) {
            return false;
        }

        $merged = array_values(array_unique(array_merge($existing, $tags)));

        if ($merged === $existing) {
            return false;
        }

        $restrictions['blocked_tags'] = $merged;
        $profile->update(['health_restrictions' => $restrictions]);

        return true;
    }

    /**
     * @return list<string>
     */
    protected function productTags(Product $product): array
    {
        $tags = $product->restriction_tags ?? [];

        if ($product->is_restricted_default) {
            $tags[] = 'restricted_default';
        }

        return array_values(array_unique(array_map('strval', $tags)));
    }
}
