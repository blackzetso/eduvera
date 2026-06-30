<?php

namespace App\Modules\Canteen\Services;

use App\Models\User;
use App\Models\UserWallet;
use App\Modules\Canteen\Exceptions\InsufficientWalletBalanceException;
use App\Modules\Canteen\Exceptions\StudentNotEligibleException;
use App\Modules\Canteen\Exceptions\WalletSettlementFailedException;
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
use Throwable;

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
        protected CanteenStudentEligibilityService $eligibility,
        protected CanteenStudentProfileSyncService $profileSync,
        protected SaleSettlementService $settlement,
    ) {}

    public function validateCart(string $studentIdRef, array $items): array
    {
        $profile = $this->resolveProfile($studentIdRef);
        $ruleRestriction = $this->restrictions->evaluate($studentIdRef, $items);
        $parentBlocks = $this->studentBlocks->evaluateCart($studentIdRef, $items);
        $restriction = $this->studentBlocks->mergeWithRestrictions($ruleRestriction, $parentBlocks);
        $total = $this->calculateTotal($items);
        $limit = $this->dailyLimit->canSpend($profile, $total);
        $wallet = $this->walletCheck($profile, $total);

        return [
            'restrictions' => $restriction,
            'daily_limit' => $limit,
            'wallet' => $wallet,
            'total' => $total,
            'can_checkout' => $restriction['allowed'] && $limit['allowed'] && $wallet['allowed'],
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

        $snapshot = $this->students->findByRef($studentRef);

        if (! $snapshot) {
            throw new StudentNotEligibleException('Student not found or not eligible for canteen purchases.');
        }

        $studentUser = $this->resolveStudentUser($studentRef);

        if ($studentUser) {
            $this->eligibility->assertCanPurchase($studentUser);
            $profile = $this->profileSync->syncFromUser($studentUser);
        } else {
            $profile = StudentProfile::query()
                ->where('student_id_ref', $snapshot->studentIdRef)
                ->first();
        }

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

        $discount = (string) ($payload['discount'] ?? '0');
        $limitExceeded = ! $limitCheck['allowed'];
        $subtotal = $total;
        $saleTotal = bcsub($subtotal, $discount, 2);

        return DB::transaction(function () use (
            $payload, $cashierUserId, $studentRef, $items, $override, $overrideReason,
            $snapshot, $studentUser, $profile, $restriction, $subtotal, $discount,
            $saleTotal, $limitCheck, $limitExceeded
        ) {
            $sale = Sale::query()->create([
                'sale_number' => $this->nextSaleNumber(),
                'student_id_ref' => $snapshot->studentIdRef,
                'student_user_id' => $studentUser?->id,
                'student_name' => $snapshot->studentName,
                'grade' => $snapshot->grade,
                'class_name' => $snapshot->className,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $saleTotal,
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

            try {
                $debitResult = $this->wallet->requestDebit(new WalletDebitRequest(
                    saleId: $sale->id,
                    studentIdRef: $snapshot->studentIdRef,
                    amount: (string) $sale->total,
                    currency: $this->settings->currency(),
                    idempotencyKey: $this->walletIdempotencyKey($sale->id),
                    metadata: ['sale_number' => $sale->sale_number],
                ));
            } catch (InsufficientWalletBalanceException|WalletSettlementFailedException $e) {
                return $this->settlement->failWalletSettlement($sale, $e->getMessage());
            } catch (Throwable $e) {
                return $this->settlement->failWalletSettlement($sale, 'Wallet settlement failed: '.$e->getMessage());
            }

            if ($override && $limitExceeded) {
                $this->dailyLimit->logOverride(
                    $snapshot->studentIdRef,
                    $saleTotal,
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

            $sale = $this->settlement->confirmWalletSettlement($sale, $debitResult);

            $this->parentNotification->queueSaleVisibility($sale->id, $snapshot->studentIdRef, [
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

            $this->audit->log('sale.completed', $sale, after: $sale->fresh()->load('items')->toArray());

            return $sale->fresh()->load(['items', 'walletReadyTransaction']);
        });
    }

    protected function resolveProfile(string $studentIdRef): ?StudentProfile
    {
        $studentUser = $this->resolveStudentUser($studentIdRef);

        if ($studentUser) {
            return $this->profileSync->syncFromUser($studentUser);
        }

        return StudentProfile::query()->where('student_id_ref', $studentIdRef)->first();
    }

    protected function resolveStudentUser(string $studentRef): ?User
    {
        $ref = trim($studentRef);

        if ($ref === '') {
            return null;
        }

        if (ctype_digit($ref)) {
            return User::query()->students()->find((int) $ref);
        }

        return User::query()->students()->where('student_code', $ref)->first();
    }

    /**
     * @return array{balance: ?float, allowed: bool, message: ?string, adapter: string}
     */
    protected function walletCheck(?StudentProfile $profile, string $cartTotal): array
    {
        $adapter = config('canteen.integration.wallet_adapter', 'pending');

        if ($adapter !== 'user_wallet' || ! $profile?->user_id) {
            $balance = null;

            if ($profile?->user_id) {
                $wallet = UserWallet::query()->where('user_id', $profile->user_id)->first();
                $balance = (float) ($wallet?->balance ?? 0);
            }

            return [
                'balance' => $balance,
                'allowed' => true,
                'message' => null,
                'adapter' => $adapter,
            ];
        }

        $wallet = UserWallet::query()->where('user_id', $profile->user_id)->first();
        $balance = (float) ($wallet?->balance ?? 0);
        $required = (float) $cartTotal;
        $allowed = $balance >= $required;

        return [
            'balance' => $balance,
            'allowed' => $allowed,
            'message' => $allowed
                ? null
                : sprintf(
                    'رصيد المحفظة غير كافٍ. المطلوب: %s، المتاح: %s.',
                    number_format($required, 2, '.', ''),
                    number_format($balance, 2, '.', ''),
                ),
            'adapter' => $adapter,
        ];
    }

    protected function walletIdempotencyKey(string $saleId): string
    {
        return 'canteen-sale-'.$saleId;
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
