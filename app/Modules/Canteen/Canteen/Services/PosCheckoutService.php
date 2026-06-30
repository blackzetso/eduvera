<?php

namespace App\Modules\Canteen\Services;

use App\Modules\Canteen\Integration\Contracts\ParentNotificationPort;
use App\Modules\Canteen\Integration\Contracts\StudentIdentityPort;
use App\Modules\Canteen\Integration\Contracts\WalletSettlementPort;
use App\Modules\Canteen\Integration\DTOs\WalletDebitRequest;
use App\Modules\Canteen\Models\Product;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Models\StudentProfile;
use App\Modules\Canteen\Support\CanteenPermission;
use App\Modules\Canteen\Support\SaleStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PosCheckoutService
{
    public function __construct(
        protected AuditService $audit,
        protected CanteenSettingsService $settings,
        protected DailyLimitService $dailyLimit,
        protected RestrictionEngineService $restrictions,
        protected StudentBlockService $studentBlocks,
        protected InventoryLedgerService $inventory,
        protected StudentIdentityPort $students,
        protected WalletSettlementPort $wallet,
        protected ParentNotificationPort $parentNotification,
    ) {}

    public function validateCart(string $studentIdRef, array $items): array
    {
        $profile = StudentProfile::query()->where('student_id_ref', $studentIdRef)->first();
        $ruleRestriction = $this->restrictions->evaluate($studentIdRef, $items);
        $parentBlocks = $this->studentBlocks->evaluateCart($studentIdRef, $items);
        $restriction = $this->studentBlocks->mergeWithRestrictions($ruleRestriction, $parentBlocks);
        $total = $this->calculateTotal($items);
        $limit = $this->dailyLimit->canSpend($profile, $total);

        return [
            'restrictions' => $restriction,
            'daily_limit' => $limit,
            'total' => $total,
            'can_checkout' => $restriction['allowed'] && $limit['allowed'],
        ];
    }

    public function checkout(array $payload, int $cashierUserId): Sale
    {
        $studentRef = $payload['student_id_ref'];
        $items = $payload['items'];
        $override = (bool) ($payload['limit_override'] ?? false);
        $overrideReason = $payload['limit_override_reason'] ?? null;

        if ($override) {
            $this->assertCanOverrideLimit();
        }

        $snapshot = $this->students->findByRef($studentRef)
            ?? StudentProfile::query()->where('student_id_ref', $studentRef)->first();

        if (! $snapshot) {
            throw new InvalidArgumentException('Student not found.');
        }

        $studentName = $snapshot instanceof StudentProfile ? $snapshot->student_name : $snapshot->studentName;
        $grade = $snapshot instanceof StudentProfile ? $snapshot->grade : $snapshot->grade;
        $className = $snapshot instanceof StudentProfile ? $snapshot->class_name : $snapshot->className;

        $profile = $snapshot instanceof StudentProfile
            ? $snapshot
            : StudentProfile::query()->where('student_id_ref', $studentRef)->first();

        $ruleRestriction = $this->restrictions->evaluate($studentRef, $items);
        $parentBlocks = $this->studentBlocks->evaluateCart($studentRef, $items);
        $restriction = $this->studentBlocks->mergeWithRestrictions($ruleRestriction, $parentBlocks);
        if (! $restriction['allowed']) {
            $this->auditRestrictionBlocks($studentRef, $items, $restriction['blocks']);
            throw new InvalidArgumentException($this->restrictionBlockMessage($restriction['blocks']));
        }

        $total = $this->calculateTotal($items);
        $limitCheck = $this->dailyLimit->canSpend($profile, $total);

        if (! $limitCheck['allowed'] && ! $override) {
            throw new InvalidArgumentException('Daily spending limit exceeded.');
        }

        return DB::transaction(function () use (
            $payload, $cashierUserId, $studentRef, $items, $override, $overrideReason,
            $studentName, $grade, $className, $restriction, $total, $limitCheck
        ) {

            $discount = (string) ($payload['discount'] ?? '0');
            $limitExceeded = ! $limitCheck['allowed'];
            $subtotal = $total;

            $sale = Sale::query()->create([
                'sale_number' => $this->nextSaleNumber(),
                'student_id_ref' => $studentRef,
                'student_name' => $studentName,
                'grade' => $grade,
                'class_name' => $className,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => bcsub($subtotal, $discount, 2),
                'payment_method' => 'wallet_ready',
                'status' => SaleStatus::PENDING_PAYMENT,
                'daily_limit_checked' => true,
                'restrictions_checked' => true,
                'limit_override_applied' => $override && $limitExceeded,
                'limit_override_reason' => $override && $limitExceeded ? $overrideReason : null,
                'limit_override_by' => $override && $limitExceeded ? auth()->id() : null,
                'cashier_user_id' => $cashierUserId,
                'sold_at' => now(),
            ]);

            if ($override && $limitExceeded) {
                $this->dailyLimit->logOverride(
                    $studentRef,
                    $total,
                    $limitCheck['limit'] ?? '0',
                    $limitCheck['remaining'] ?? '0',
                    $overrideReason ?? 'Manager override',
                    $sale->id,
                );
            }

            foreach ($items as $item) {
                $product = Product::query()->findOrFail($item['product_id']);
                $qty = (string) $item['quantity'];
                $lineTotal = bcmul((string) $product->selling_price, $qty, 2);

                $sale->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'unit_price' => $product->selling_price,
                    'quantity' => $qty,
                    'line_total' => $lineTotal,
                ]);

                $this->inventory->recordSale($product->id, $qty, $sale->id);
            }

            $idempotencyKey = 'canteen-sale-'.$sale->id;
            $this->wallet->requestDebit(new WalletDebitRequest(
                saleId: $sale->id,
                studentIdRef: $studentRef,
                amount: (string) $sale->total,
                currency: $this->settings->currency(),
                idempotencyKey: $idempotencyKey,
                metadata: ['sale_number' => $sale->sale_number],
            ));

            $this->parentNotification->queueSaleVisibility($sale->id, $studentRef, [
                'sale_number' => $sale->sale_number,
                'total' => (string) $sale->total,
                'sold_at' => $sale->sold_at?->toIso8601String(),
                'items' => $sale->items()->get()->toArray(),
            ]);

            if (! empty($restriction['warnings'])) {
                $this->audit->log('restriction.warning_triggered', $sale, after: [
                    'student_id_ref' => $studentRef,
                    'warnings' => $restriction['warnings'],
                ]);
            }

            $this->audit->log('sale.pending_settlement', $sale, after: $sale->fresh()->load('items')->toArray());

            return $sale->fresh()->load(['items', 'walletReadyTransaction']);
        });
    }

    protected function assertCanOverrideLimit(): void
    {
        $user = auth()->user();

        if (! $user || ! CanteenPermission::allows($user, 'canteen.student-limits.override')) {
            throw new InvalidArgumentException('Unauthorized daily limit override.');
        }
    }

    protected function calculateTotal(array $items): string
    {
        $total = '0';
        foreach ($items as $item) {
            $product = Product::query()->find($item['product_id']);
            if (! $product) {
                throw new InvalidArgumentException('Invalid product in cart.');
            }
            $total = bcadd($total, bcmul((string) $product->selling_price, (string) $item['quantity'], 2), 2);
        }

        return $total;
    }

    protected function nextSaleNumber(): string
    {
        return 'CN-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    protected function restrictionBlockMessage(array $blocks): string
    {
        if (empty($blocks)) {
            return 'Cart violates product restrictions.';
        }

        $lines = collect($blocks)
            ->map(fn ($b) => ($b['product_name'] ?? 'Item').': '.($b['message'] ?? 'Blocked'))
            ->unique()
            ->values()
            ->all();

        return 'Purchase blocked — '.implode(' | ', $lines);
    }

    /**
     * @param  array<int, array{product_id: string, quantity: string}>  $items
     * @param  list<array<string, mixed>>  $blocks
     */
    protected function auditRestrictionBlocks(string $studentRef, array $items, array $blocks): void
    {
        if (empty($blocks)) {
            return;
        }

        $subject = StudentProfile::query()->where('student_id_ref', $studentRef)->first()
            ?? Product::query()->find($items[0]['product_id'] ?? null);

        if (! $subject) {
            return;
        }

        $this->audit->log('restriction.block_triggered', $subject, after: [
            'student_id_ref' => $studentRef,
            'blocks' => $blocks,
            'items' => $items,
        ]);
    }
}
