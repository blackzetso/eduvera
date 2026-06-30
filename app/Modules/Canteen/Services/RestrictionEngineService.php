<?php

namespace App\Modules\Canteen\Services;

use App\Modules\Canteen\Models\Product;
use App\Modules\Canteen\Models\RestrictionRule;
use App\Modules\Canteen\Models\SaleItem;
use App\Modules\Canteen\Models\StudentRestrictionAssignment;
use Illuminate\Support\Carbon;

class RestrictionEngineService
{
    /**
     * @param  array<int, array{product_id: string, quantity: string}>  $cartItems
     * @return array{
     *     allowed: bool,
     *     blocks: list<array<string, mixed>>,
     *     warnings: list<array<string, mixed>>,
     *     violations: list<array<string, mixed>>
     * }
     */
    public function evaluate(string $studentIdRef, array $cartItems): array
    {
        $blocks = [];
        $warnings = [];
        $rules = $this->activeRulesForStudent($studentIdRef);

        if ($rules->isEmpty()) {
            return $this->result($blocks, $warnings);
        }

        $products = Product::query()
            ->with('category:id,slug,name')
            ->whereIn('id', collect($cartItems)->pluck('product_id'))
            ->get()
            ->keyBy('id');

        foreach ($cartItems as $item) {
            $product = $products->get($item['product_id']);
            if (! $product) {
                continue;
            }

            foreach ($rules as $rule) {
                $violation = $this->checkRule($rule, $product, (string) $item['quantity'], $studentIdRef);
                if ($violation) {
                    if ($rule->severity === 'warn') {
                        $warnings[] = $violation;
                    } else {
                        $blocks[] = $violation;
                    }
                }
            }
        }

        return $this->result($blocks, $warnings);
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @param  list<array<string, mixed>>  $warnings
     * @return array{allowed: bool, blocks: list<array<string, mixed>>, warnings: list<array<string, mixed>>, violations: list<array<string, mixed>>}
     */
    protected function result(array $blocks, array $warnings): array
    {
        return [
            'allowed' => empty($blocks),
            'blocks' => $blocks,
            'warnings' => $warnings,
            'violations' => array_merge($blocks, $warnings),
        ];
    }

    protected function activeRulesForStudent(string $studentIdRef)
    {
        $today = today();

        return StudentRestrictionAssignment::query()
            ->with('rule')
            ->where('student_id_ref', $studentIdRef)
            ->whereHas('rule', fn ($q) => $q->where('is_active', true))
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $today))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
            ->get()
            ->pluck('rule')
            ->filter();
    }

    protected function checkRule(RestrictionRule $rule, Product $product, string $qty, string $studentIdRef): ?array
    {
        $config = $rule->config ?? [];
        $triggered = match ($rule->rule_type) {
            'block_tag' => $this->hasTagOverlap($product, $config['tags'] ?? []),
            'require_tag' => ! $this->hasAllTags($product, $config['tags'] ?? []),
            'block_product' => in_array($product->id, $config['product_ids'] ?? [], true),
            'block_category' => in_array($product->category?->slug, $config['category_slugs'] ?? [], true),
            'max_qty_per_day' => $this->exceedsDailyTagLimit($studentIdRef, $product, $config, $qty),
            default => false,
        };

        if (! $triggered) {
            return null;
        }

        return $this->violationPayload($rule, $product, $this->violationMessage($rule, $product));
    }

    protected function violationMessage(RestrictionRule $rule, Product $product): string
    {
        return match ($rule->rule_type) {
            'block_tag' => "{$product->name} is restricted ({$rule->name}).",
            'require_tag' => "{$product->name} does not meet requirement ({$rule->name}).",
            'block_product' => "{$product->name} is blocked.",
            'block_category' => "Category restricted for {$product->name}.",
            'max_qty_per_day' => "Daily limit exceeded for {$product->name}.",
            default => "{$product->name} violates {$rule->name}.",
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function violationPayload(RestrictionRule $rule, Product $product, string $message): array
    {
        return [
            'rule' => $rule->code,
            'rule_name' => $rule->name,
            'rule_type' => $rule->rule_type,
            'severity' => $rule->severity,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'message' => $message,
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

    protected function hasTagOverlap(Product $product, array $tags): bool
    {
        if (empty($tags)) {
            return false;
        }

        return (bool) array_intersect($this->productTags($product), $tags);
    }

    protected function hasAllTags(Product $product, array $required): bool
    {
        if (empty($required)) {
            return false;
        }

        $productTags = $this->productTags($product);

        return empty(array_diff($required, $productTags));
    }

    protected function exceedsDailyTagLimit(string $studentIdRef, Product $product, array $config, string $qty): bool
    {
        $tags = $config['tags'] ?? [];
        $max = (float) ($config['max'] ?? 0);
        if ($max <= 0 || ! $this->hasTagOverlap($product, $tags)) {
            return false;
        }

        $todayQty = SaleItem::query()
            ->whereHas('sale', fn ($q) => $q
                ->where('student_id_ref', $studentIdRef)
                ->where('status', 'completed')
                ->whereDate('sold_at', today()))
            ->whereHas('product', function ($q) use ($tags) {
                $q->where(function ($inner) use ($tags) {
                    foreach ($tags as $tag) {
                        $inner->orWhereJsonContains('restriction_tags', $tag);
                    }
                });
            })
            ->sum('quantity');

        return ((float) $todayQty + (float) $qty) > $max;
    }
}
