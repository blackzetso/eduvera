<?php

namespace App\Modules\Canteen\Services;

use App\Modules\Canteen\Models\LimitOverrideLog;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Models\StudentProfile;
use Illuminate\Support\Carbon;

class DailyLimitService
{
    public function __construct(protected CanteenSettingsService $settings) {}

    public function getLimit(?StudentProfile $profile): ?string
    {
        if ($profile?->daily_spending_limit !== null) {
            return (string) $profile->daily_spending_limit;
        }

        return $this->settings->defaultDailyLimit();
    }

    public function spentToday(string $studentIdRef, ?Carbon $date = null): string
    {
        $date = $date ?? today();

        $sum = Sale::query()
            ->where('student_id_ref', $studentIdRef)
            ->where('status', 'completed')
            ->whereDate('sold_at', $date)
            ->sum('total');

        return (string) $sum;
    }

    public function remaining(?StudentProfile $profile): ?string
    {
        $limit = $this->getLimit($profile);
        if ($limit === null || ! $profile) {
            return null;
        }

        $spent = $this->spentToday($profile->student_id_ref);

        return bcsub($limit, $spent, 2);
    }

    public function canSpend(?StudentProfile $profile, string $amount): array
    {
        $limit = $profile ? $this->getLimit($profile) : null;

        if ($limit === null) {
            return ['allowed' => true, 'limit' => null, 'remaining' => null, 'spent' => '0'];
        }

        $spent = $this->spentToday($profile->student_id_ref);
        $remaining = bcsub($limit, $spent, 2);
        $allowed = bccomp($remaining, $amount, 2) >= 0;

        return [
            'allowed' => $allowed,
            'limit' => $limit,
            'remaining' => $remaining,
            'spent' => $spent,
        ];
    }

    public function logOverride(
        string $studentIdRef,
        string $attemptedAmount,
        string $dailyLimit,
        string $remainingBefore,
        string $reason,
        ?string $saleId = null,
    ): LimitOverrideLog {
        return LimitOverrideLog::query()->create([
            'sale_id' => $saleId,
            'student_id_ref' => $studentIdRef,
            'attempted_amount' => $attemptedAmount,
            'daily_limit' => $dailyLimit,
            'remaining_before' => $remainingBefore,
            'override_by' => auth()->id(),
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
