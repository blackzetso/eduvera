<?php

namespace App\Modules\Canteen\Services;

use App\Modules\Canteen\Models\Product;
use App\Modules\Canteen\Models\StudentProfile;

class CanteenHealthRestrictionService
{
    /**
     * @return list<string>
     */
    public function blockedTags(StudentProfile $profile): array
    {
        $restrictions = $profile->health_restrictions ?? [];

        return array_values(array_unique(array_filter(array_map(
            'strval',
            array_merge(
                $restrictions['blocked_tags'] ?? [],
                $restrictions['allergies'] ?? [],
            )
        ))));
    }

    public function blockReasonForStudent(?StudentProfile $profile): ?string
    {
        if (! $profile) {
            return null;
        }

        if ((bool) ($profile->metadata['guardian_purchase_blocked'] ?? false)) {
            return 'Guardian has blocked canteen purchases for this student.';
        }

        $notes = trim((string) ($profile->health_restrictions['notes'] ?? ''));

        if ($notes !== '' && ($profile->health_restrictions['block_all_purchases'] ?? false)) {
            return 'Health restrictions block all canteen purchases: '.$notes;
        }

        return null;
    }

    /**
     * @param  array<int, array{product_id: string, quantity: string}>  $cartItems
     * @return array{allowed: bool, blocks: list<array<string, mixed>>}
     */
    public function evaluateCart(?StudentProfile $profile, array $cartItems): array
    {
        if (! $profile) {
            return ['allowed' => true, 'blocks' => []];
        }

        $hardBlock = $this->blockReasonForStudent($profile);

        if ($hardBlock) {
            return [
                'allowed' => false,
                'blocks' => [[
                    'type' => 'health_restriction',
                    'message' => $hardBlock,
                ]],
            ];
        }

        $blockedTags = $this->blockedTags($profile);

        if (empty($blockedTags)) {
            return ['allowed' => true, 'blocks' => []];
        }

        $products = Product::query()
            ->whereIn('id', collect($cartItems)->pluck('product_id'))
            ->get()
            ->keyBy('id');

        $blocks = [];

        foreach ($cartItems as $item) {
            $product = $products->get($item['product_id']);

            if (! $product) {
                continue;
            }

            $matched = array_values(array_intersect($blockedTags, $this->productTags($product)));

            if (! empty($matched)) {
                $blocks[] = [
                    'type' => 'health_restriction',
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'matched_tags' => $matched,
                    'message' => 'Product blocked due to health restriction: '.implode(', ', $matched),
                ];
            }
        }

        return [
            'allowed' => empty($blocks),
            'blocks' => $blocks,
        ];
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

        return array_values(array_unique($tags));
    }
}
