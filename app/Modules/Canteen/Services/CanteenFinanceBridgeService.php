<?php

namespace App\Modules\Canteen\Services;

use App\Modules\Canteen\Models\CanteenFinanceEntry;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Support\SaleStatus;
use Illuminate\Support\Carbon;

class CanteenFinanceBridgeService
{
    public function spentTodayForStudent(int $studentUserId, ?Carbon $date = null): string
    {
        $date = $date ?? today();

        $fromLedger = CanteenFinanceEntry::query()
            ->where('student_user_id', $studentUserId)
            ->where('entry_type', CanteenFinanceEntry::TYPE_PURCHASE)
            ->where('status', CanteenFinanceEntry::STATUS_POSTED)
            ->whereDate('posted_at', $date)
            ->sum('amount');

        if ($fromLedger > 0) {
            return (string) $fromLedger;
        }

        return (string) Sale::query()
            ->where('student_user_id', $studentUserId)
            ->where('status', SaleStatus::COMPLETED)
            ->whereDate('sold_at', $date)
            ->sum('total');
    }

    public function spentInRangeForStudent(int $studentUserId, Carbon $from, Carbon $to): string
    {
        $fromLedger = CanteenFinanceEntry::query()
            ->where('student_user_id', $studentUserId)
            ->where('entry_type', CanteenFinanceEntry::TYPE_PURCHASE)
            ->where('status', CanteenFinanceEntry::STATUS_POSTED)
            ->whereBetween('posted_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->sum('amount');

        if ($fromLedger > 0) {
            return (string) $fromLedger;
        }

        return (string) Sale::query()
            ->where('student_user_id', $studentUserId)
            ->where('status', SaleStatus::COMPLETED)
            ->whereBetween('sold_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->sum('total');
    }

    public function spentTodayForFamily(int $guardianUserId, ?Carbon $date = null): string
    {
        $date = $date ?? today();

        return (string) CanteenFinanceEntry::query()
            ->where('guardian_user_id', $guardianUserId)
            ->where('ledger_scope', CanteenFinanceEntry::SCOPE_FAMILY)
            ->where('entry_type', CanteenFinanceEntry::TYPE_PURCHASE)
            ->where('status', CanteenFinanceEntry::STATUS_POSTED)
            ->whereDate('posted_at', $date)
            ->sum('amount');
    }
}
