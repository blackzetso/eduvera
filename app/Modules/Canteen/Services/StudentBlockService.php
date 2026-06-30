<?php

namespace App\Modules\Canteen\Services;

use App\Modules\Canteen\Models\Product;
use App\Modules\Canteen\Models\StudentBlockedCategory;
use App\Modules\Canteen\Models\StudentBlockedProduct;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StudentBlockService
{
    public const SOURCE_PARENT_REQUEST = 'parent_request';

    public const SOURCE_ADMIN = 'admin';

    public function __construct(
        protected AuditService $audit,
    ) {}

    public function blockProduct(string $studentIdRef, string $productId, array $data = []): StudentBlockedProduct
    {
        $this->expireDueBlocks();
        $schedule = $this->resolveSchedule($data);
        $isTemporary = $schedule['expires_at'] !== null;

        $block = StudentBlockedProduct::query()->updateOrCreate(
            ['student_id_ref' => $studentIdRef, 'product_id' => $productId],
            [
                'block_source' => $data['block_source'] ?? self::SOURCE_PARENT_REQUEST,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_active' => true,
                'starts_at' => $schedule['starts_at'],
                'expires_at' => $schedule['expires_at'],
                'created_by' => auth()->id(),
            ],
        );

        $action = $isTemporary ? 'student_blocked_product.temporary_added' : 'student_blocked_product.added';
        $this->audit->log($action, $block->load('product'), after: $block->toArray());

        return $block;
    }

    public function unblockProduct(StudentBlockedProduct $block): void
    {
        $before = $block->load('product')->toArray();
        $block->update(['is_active' => false]);
        $this->audit->log('student_blocked_product.removed', $block, before: $before, after: $block->fresh()->toArray());
    }

    public function blockCategory(string $studentIdRef, string $categoryId, array $data = []): StudentBlockedCategory
    {
        $this->expireDueBlocks();
        $schedule = $this->resolveSchedule($data);
        $isTemporary = $schedule['expires_at'] !== null;

        $block = StudentBlockedCategory::query()->updateOrCreate(
            ['student_id_ref' => $studentIdRef, 'category_id' => $categoryId],
            [
                'block_source' => $data['block_source'] ?? self::SOURCE_PARENT_REQUEST,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_active' => true,
                'starts_at' => $schedule['starts_at'],
                'expires_at' => $schedule['expires_at'],
                'created_by' => auth()->id(),
            ],
        );

        $action = $isTemporary ? 'student_blocked_category.temporary_added' : 'student_blocked_category.added';
        $this->audit->log($action, $block->load('category'), after: $block->toArray());

        return $block;
    }

    public function unblockCategory(StudentBlockedCategory $block): void
    {
        $before = $block->load('category')->toArray();
        $block->update(['is_active' => false]);
        $this->audit->log('student_blocked_category.removed', $block, before: $before, after: $block->fresh()->toArray());
    }

    public function expireDueBlocks(): int
    {
        $now = Carbon::now();
        $count = 0;

        $expiredProducts = StudentBlockedProduct::query()
            ->where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->get();

        foreach ($expiredProducts as $block) {
            $before = $block->toArray();
            $block->update(['is_active' => false]);
            $this->audit->log('student_blocked_product.expired', $block, before: $before, after: $block->fresh()->toArray());
            $count++;
        }

        $expiredCategories = StudentBlockedCategory::query()
            ->where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->get();

        foreach ($expiredCategories as $block) {
            $before = $block->toArray();
            $block->update(['is_active' => false]);
            $this->audit->log('student_blocked_category.expired', $block, before: $before, after: $block->fresh()->toArray());
            $count++;
        }

        return $count;
    }

    /**
     * @return array{
     *     product_ids: list<string>,
     *     category_ids: list<string>,
     *     products: list<array<string, mixed>>,
     *     categories: list<array<string, mixed>>
     * }
     */
    public function summaryForStudent(string $studentIdRef): array
    {
        $this->expireDueBlocks();

        $products = StudentBlockedProduct::query()
            ->currentlyEffective()
            ->with('product.category:id,name,name_ar')
            ->where('student_id_ref', $studentIdRef)
            ->get();

        $categories = StudentBlockedCategory::query()
            ->currentlyEffective()
            ->with('category:id,name,name_ar,slug')
            ->where('student_id_ref', $studentIdRef)
            ->get();

        return [
            'product_ids' => $products->pluck('product_id')->all(),
            'category_ids' => $categories->pluck('category_id')->all(),
            'products' => $products->map(fn (StudentBlockedProduct $b) => $this->formatProductBlock($b))->values()->all(),
            'categories' => $categories->map(fn (StudentBlockedCategory $b) => $this->formatCategoryBlock($b))->values()->all(),
        ];
    }

    /**
     * @param  array<int, array{product_id: string, quantity: string}>  $cartItems
     * @return array{allowed: bool, blocks: list<array<string, mixed>>, warnings: list<array<string, mixed>>, violations: list<array<string, mixed>>}
     */
    public function evaluateCart(string $studentIdRef, array $cartItems): array
    {
        $this->expireDueBlocks();

        $blocks = [];
        $productIds = collect($cartItems)->pluck('product_id')->filter()->unique()->values();
        if ($productIds->isEmpty()) {
            return $this->emptyResult();
        }

        $blockedProducts = StudentBlockedProduct::query()
            ->currentlyEffective()
            ->with('product.category:id,name,name_ar')
            ->where('student_id_ref', $studentIdRef)
            ->whereIn('product_id', $productIds)
            ->get()
            ->keyBy('product_id');

        $blockedCategories = StudentBlockedCategory::query()
            ->currentlyEffective()
            ->with('category:id,name,name_ar,slug')
            ->where('student_id_ref', $studentIdRef)
            ->get()
            ->keyBy('category_id');

        $products = Product::query()
            ->with('category:id,name,name_ar,slug')
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        foreach ($cartItems as $item) {
            $product = $products->get($item['product_id']);
            if (! $product) {
                continue;
            }

            $productBlock = $blockedProducts->get($product->id);
            if ($productBlock) {
                $blocks[] = $this->violationPayload($product, 'product', $productBlock);
                continue;
            }

            $categoryBlock = $blockedCategories->get($product->category_id);
            if ($categoryBlock) {
                $blocks[] = $this->violationPayload($product, 'category', $categoryBlock);
            }
        }

        return [
            'allowed' => empty($blocks),
            'blocks' => $blocks,
            'warnings' => [],
            'violations' => $blocks,
        ];
    }

    /**
     * @return array{allowed: bool, blocks: list<array<string, mixed>>, warnings: list<array<string, mixed>>, violations: list<array<string, mixed>>}
     */
    public function mergeWithRestrictions(array $ruleResult, array $parentResult): array
    {
        $blocks = array_merge($ruleResult['blocks'] ?? [], $parentResult['blocks'] ?? []);
        $warnings = $ruleResult['warnings'] ?? [];

        return [
            'allowed' => empty($blocks),
            'blocks' => $blocks,
            'warnings' => $warnings,
            'violations' => array_merge($blocks, $warnings),
        ];
    }

    public function checkProduct(string $studentIdRef, Product $product): ?array
    {
        $this->expireDueBlocks();

        $productBlock = StudentBlockedProduct::query()
            ->currentlyEffective()
            ->where('student_id_ref', $studentIdRef)
            ->where('product_id', $product->id)
            ->first();

        if ($productBlock) {
            return $this->violationPayload($product, 'product', $productBlock);
        }

        $categoryBlock = StudentBlockedCategory::query()
            ->currentlyEffective()
            ->where('student_id_ref', $studentIdRef)
            ->where('category_id', $product->category_id)
            ->first();

        if ($categoryBlock) {
            return $this->violationPayload($product, 'category', $categoryBlock);
        }

        return null;
    }

    /**
     * @return array{products: array<string, list<array<string, mixed>>>, categories: array<string, list<array<string, mixed>>>}
     */
    public function blocksForStudents(Collection $studentRefs): array
    {
        $this->expireDueBlocks();

        $products = StudentBlockedProduct::query()
            ->with('product.category:id,name,name_ar')
            ->whereIn('student_id_ref', $studentRefs)
            ->where(function ($q) {
                $q->currentlyEffective()
                    ->orWhere(function ($q2) {
                        $q2->where('is_active', false)
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '<=', Carbon::now());
                    });
            })
            ->orderByDesc('created_at')
            ->get();

        $categories = StudentBlockedCategory::query()
            ->with('category:id,name,name_ar,slug')
            ->whereIn('student_id_ref', $studentRefs)
            ->where(function ($q) {
                $q->currentlyEffective()
                    ->orWhere(function ($q2) {
                        $q2->where('is_active', false)
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '<=', Carbon::now());
                    });
            })
            ->orderByDesc('created_at')
            ->get();

        return [
            'products' => $products->groupBy('student_id_ref')
                ->map(fn ($items) => $items->map(fn (StudentBlockedProduct $b) => $this->formatProductBlock($b))->values()->all())
                ->all(),
            'categories' => $categories->groupBy('student_id_ref')
                ->map(fn ($items) => $items->map(fn (StudentBlockedCategory $b) => $this->formatCategoryBlock($b))->values()->all())
                ->all(),
        ];
    }

    /**
     * @return array{starts_at: \Illuminate\Support\Carbon, expires_at: ?\Illuminate\Support\Carbon}
     */
    protected function resolveSchedule(array $data): array
    {
        $startsAt = Carbon::now();
        $type = $data['restriction_type'] ?? 'permanent';

        if ($type !== 'temporary') {
            return ['starts_at' => $startsAt, 'expires_at' => null];
        }

        $days = (int) ($data['duration_days'] ?? 0);
        if ($days < 1) {
            $days = 7;
        }

        return [
            'starts_at' => $startsAt,
            'expires_at' => $startsAt->copy()->addDays($days),
        ];
    }

    protected function formatProductBlock(StudentBlockedProduct $block): array
    {
        $meta = $this->restrictionMeta($block);

        return [
            'id' => $block->id,
            'product_id' => $block->product_id,
            'product_name' => $block->product?->name_ar ?? $block->product?->name,
            'category' => $block->product?->category?->name_ar ?? $block->product?->category?->name,
            'status' => $meta['status'],
            'restriction_type' => $meta['restriction_type'],
            'badge_class' => $meta['badge_class'],
            'badge_label' => $meta['badge_label'],
            'remaining_label' => $meta['remaining_label'],
            'remaining_days' => $meta['remaining_days'],
            'starts_at' => $block->starts_at?->toDateString(),
            'expires_at' => $block->expires_at?->toDateString(),
            'block_source' => $block->block_source,
            'reason' => $block->reason,
            'notes' => $block->notes,
            'is_effective' => $block->isCurrentlyEffective(),
            'created_by' => $block->created_by,
            'created_at' => $block->created_at?->toDateTimeString(),
        ];
    }

    protected function formatCategoryBlock(StudentBlockedCategory $block): array
    {
        $meta = $this->restrictionMeta($block);

        return [
            'id' => $block->id,
            'category_id' => $block->category_id,
            'category_name' => $block->category?->name_ar ?? $block->category?->name,
            'status' => $meta['status'],
            'restriction_type' => $meta['restriction_type'],
            'badge_class' => $meta['badge_class'],
            'badge_label' => $meta['badge_label'],
            'remaining_label' => $meta['remaining_label'],
            'remaining_days' => $meta['remaining_days'],
            'starts_at' => $block->starts_at?->toDateString(),
            'expires_at' => $block->expires_at?->toDateString(),
            'block_source' => $block->block_source,
            'reason' => $block->reason,
            'notes' => $block->notes,
            'is_effective' => $block->isCurrentlyEffective(),
            'created_by' => $block->created_by,
            'created_at' => $block->created_at?->toDateTimeString(),
        ];
    }

    /**
     * @return array{
     *     status: string,
     *     restriction_type: string,
     *     badge_class: string,
     *     badge_label: string,
     *     remaining_label: string,
     *     remaining_days: ?int
     * }
     */
    protected function restrictionMeta(StudentBlockedProduct|StudentBlockedCategory $block): array
    {
        if ($block->isExpired() || ($block->expires_at && ! $block->is_active)) {
            return [
                'status' => 'expired',
                'restriction_type' => 'temporary',
                'badge_class' => 'bg-secondary',
                'badge_label' => 'منتهي',
                'remaining_label' => 'منتهي',
                'remaining_days' => null,
            ];
        }

        if ($block->isPermanent()) {
            return [
                'status' => 'active',
                'restriction_type' => 'permanent',
                'badge_class' => 'bg-danger',
                'badge_label' => 'دائم',
                'remaining_label' => 'دائم',
                'remaining_days' => null,
            ];
        }

        $remaining = $block->remainingDays();

        return [
            'status' => 'active',
            'restriction_type' => 'temporary',
            'badge_class' => 'bg-warning text-dark',
            'badge_label' => 'مؤقت',
            'remaining_label' => $remaining === null ? 'مؤقت' : "ينتهي خلال {$remaining} يوم",
            'remaining_days' => $remaining,
        ];
    }

    /**
     * @return array{allowed: bool, blocks: list<array<string, mixed>>, warnings: list<array<string, mixed>>, violations: list<array<string, mixed>>}
     */
    protected function emptyResult(): array
    {
        return [
            'allowed' => true,
            'blocks' => [],
            'warnings' => [],
            'violations' => [],
        ];
    }

    protected function violationPayload(Product $product, string $type, StudentBlockedProduct|StudentBlockedCategory $block): array
    {
        $name = $product->name_ar ?? $product->name;
        $message = 'هذا المنتج محظور لهذا الطالب.';
        if ($block->reason) {
            $message .= ' '.$block->reason;
        }

        return [
            'type' => 'parent_block',
            'block_type' => $type,
            'product_id' => $product->id,
            'product_name' => $name,
            'category_id' => $product->category_id,
            'category_name' => $product->category?->name_ar ?? $product->category?->name,
            'block_source' => $block->block_source,
            'reason' => $block->reason,
            'notes' => $block->notes,
            'message' => $message,
            'severity' => 'block',
        ];
    }
}
