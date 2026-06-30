<?php

namespace App\Modules\Canteen\Services;

use App\Models\User;
use App\Models\UserWallet;
use App\Modules\Canteen\Integration\Contracts\GuardianIntegrationPort;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Models\StudentProfile;
use App\Modules\Canteen\Support\SaleStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class CanteenParentDashboardService
{
    public function __construct(
        protected GuardianIntegrationPort $guardians,
        protected DailyLimitService $dailyLimit,
        protected StudentBlockService $studentBlocks,
        protected CanteenGuardianSpendingService $guardianSpending,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summaryForGuardian(User $guardian): array
    {
        $children = $this->linkedStudents($guardian);
        $household = $this->guardianSpending->canHouseholdSpend($guardian, '0');

        return [
            'guardian_id' => $guardian->id,
            'wallet_adapter' => config('canteen.integration.wallet_adapter', 'pending'),
            'children_count' => $children->count(),
            'household_spending' => [
                'limit' => $household['limit'],
                'spent_today' => $household['spent'],
                'remaining' => $household['remaining'],
            ],
            'children' => $children
                ->map(fn (User $student) => $this->childSummary($student))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function childSummary(User $student): array
    {
        $profile = $this->profileForStudent($student);
        $studentRef = (string) $student->id;
        $blocks = $this->studentBlocks->blocksForStudents(collect([$studentRef]));

        return [
            'student_id' => $student->id,
            'student_id_ref' => $studentRef,
            'student_name' => $student->name,
            'student_code' => $student->student_code,
            'daily_limit' => [
                'limit' => $this->dailyLimit->getLimit($profile),
                'spent_today' => $this->dailyLimit->spentToday($studentRef),
                'remaining' => $this->dailyLimit->remaining($profile),
            ],
            'wallet' => $this->walletSnapshot($student),
            'purchases_today_total' => $this->spentToday($studentRef),
            'active_blocks_count' => count($blocks['products'][$studentRef] ?? [])
                + count($blocks['categories'][$studentRef] ?? []),
            'health_restrictions' => $profile?->health_restrictions ?? [],
        ];
    }

    public function purchasesForStudent(User $student, int $perPage = 15): LengthAwarePaginator
    {
        return Sale::query()
            ->with(['items'])
            ->where(fn ($q) => $q
                ->where('student_user_id', $student->id)
                ->orWhere('student_id_ref', (string) $student->id))
            ->where('status', SaleStatus::COMPLETED)
            ->orderByDesc('sold_at')
            ->paginate($perPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function purchaseDetail(User $student, Sale $sale): array
    {
        $this->assertSaleBelongsToStudent($student, $sale);

        $sale->load(['items', 'walletReadyTransaction']);

        return [
            'sale' => $sale,
            'wallet_settlement' => [
                'adapter' => config('canteen.integration.wallet_adapter', 'pending'),
                'status' => $sale->walletReadyTransaction?->status,
                'external_wallet_tx_id' => $sale->walletReadyTransaction?->external_wallet_tx_id,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function spendingSummary(User $student, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? today()->subDays(6);
        $to = $to ?? today();
        $studentRef = (string) $student->id;

        $sales = Sale::query()
            ->where(fn ($q) => $q
                ->where('student_user_id', $student->id)
                ->orWhere('student_id_ref', $studentRef))
            ->where('status', SaleStatus::COMPLETED)
            ->whereBetween('sold_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('sold_at')
            ->get(['sold_at', 'total']);

        $byDay = [];

        foreach ($sales as $sale) {
            $day = $sale->sold_at?->toDateString() ?? today()->toDateString();
            $byDay[$day] = bcadd((string) ($byDay[$day] ?? '0'), (string) $sale->total, 2);
        }

        return [
            'student_id' => $student->id,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'total' => (string) $sales->sum('total'),
            'daily_totals' => $byDay,
            'transaction_count' => $sales->count(),
        ];
    }

    protected function linkedStudents(User $guardian): \Illuminate\Support\Collection
    {
        return $guardian->students()->orderBy('name')->get();
    }

    protected function profileForStudent(User $student): ?StudentProfile
    {
        return StudentProfile::query()
            ->where(fn ($q) => $q
                ->where('user_id', $student->id)
                ->orWhere('student_id_ref', (string) $student->id))
            ->first();
    }

    /**
     * @return array{balance: ?float, status: string, adapter: string}
     */
    protected function walletSnapshot(User $student): array
    {
        $adapter = config('canteen.integration.wallet_adapter', 'pending');

        if ($adapter !== 'user_wallet') {
            return [
                'balance' => null,
                'status' => 'queued',
                'adapter' => $adapter,
            ];
        }

        $wallet = UserWallet::query()->where('user_id', $student->id)->first();

        return [
            'balance' => (float) ($wallet?->balance ?? 0),
            'status' => 'active',
            'adapter' => 'user_wallet',
        ];
    }

    protected function spentToday(string $studentRef): string
    {
        return $this->dailyLimit->spentToday($studentRef);
    }

    protected function assertSaleBelongsToStudent(User $student, Sale $sale): void
    {
        $owns = ($sale->student_user_id === $student->id)
            || ($sale->student_id_ref === (string) $student->id);

        if (! $owns) {
            abort(404, 'Sale not found for this student.');
        }
    }
}
