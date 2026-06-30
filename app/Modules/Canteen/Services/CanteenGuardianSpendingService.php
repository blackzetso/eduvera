<?php

namespace App\Modules\Canteen\Services;

use App\Models\User;
use App\Modules\Canteen\Integration\Contracts\GuardianIntegrationPort;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Support\SaleStatus;
use Illuminate\Support\Carbon;

class CanteenGuardianSpendingService
{
    public function __construct(protected GuardianIntegrationPort $guardians) {}

    public function spentTodayForGuardian(User $guardian, ?Carbon $date = null): string
    {
        $date = $date ?? today();
        $studentRefs = $this->guardians->studentRefsForGuardian($guardian);

        if (empty($studentRefs)) {
            return '0';
        }

        $sum = Sale::query()
            ->whereIn('student_id_ref', $studentRefs)
            ->where('status', SaleStatus::COMPLETED)
            ->whereDate('sold_at', $date)
            ->sum('total');

        return (string) $sum;
    }

    public function guardianDailyLimit(User $guardian): ?string
    {
        $limit = config('canteen.guardian.default_household_daily_limit');

        return $limit !== null ? (string) $limit : null;
    }

    /**
     * @return array{allowed: bool, limit: ?string, spent: string, remaining: ?string}
     */
    public function canHouseholdSpend(User $guardian, string $amount): array
    {
        $limit = $this->guardianDailyLimit($guardian);

        if ($limit === null) {
            return [
                'allowed' => true,
                'limit' => null,
                'spent' => $this->spentTodayForGuardian($guardian),
                'remaining' => null,
            ];
        }

        $spent = $this->spentTodayForGuardian($guardian);
        $remaining = bcsub($limit, $spent, 2);

        return [
            'allowed' => bccomp($remaining, $amount, 2) >= 0,
            'limit' => $limit,
            'spent' => $spent,
            'remaining' => $remaining,
        ];
    }
}
