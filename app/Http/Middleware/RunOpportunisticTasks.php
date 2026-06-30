<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\SystemTask;
use App\Services\WalletService;
use App\Services\ExchangeRateService;
use Illuminate\Support\Facades\Log;

class RunOpportunisticTasks
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Defer heavy sync work until after the HTTP response is sent.
        // Without this, `php artisan serve` blocks every page while Bunny/API tasks run.
        dispatch(fn () => $this->runPendingTasks())->afterResponse();

        return $response;
    }

    /**
     * Run any pending tasks
     */
    public function runPendingTasks(): void
    {
        try {
            // Task 1: Sync consumption (daily)
            $this->runConsumptionSync();

            // Task 2: Update exchange rate (every 6 hours)
            $this->runExchangeRateUpdate();

            // Task 3: Attendance thresholds (daily)
            $this->runAttendanceThresholdCheck();

            // Task 4: Sync live stream attendances (hourly)
            $this->runLiveStreamAttendanceSync();

            // Task 5: Close daily absence coverage (end of school day)
            $this->runCloseDailyCoverage();

        } catch (\Exception $e) {
            Log::error('Opportunistic task error: ' . $e->getMessage());
        }
    }

    /**
     * Run consumption sync task
     */
    protected function runConsumptionSync(): void
    {
        $task = SystemTask::getTask('sync_consumption', 86400); // 24 hours

        if (!$task->shouldRun()) {
            return;
        }

        $task->markAsRunning();

        try {
            $walletService = app(WalletService::class);
            $result = $walletService->syncConsumptionFromBunny();

            $task->saveResult($result);

            Log::info('Opportunistic consumption sync completed', [
                'storage_gb' => $result['storage_gb'] ?? 0,
                'bandwidth_gb' => $result['bandwidth_gb'] ?? 0,
                'adjustment' => $result['adjustment'] ?? 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Consumption sync failed: ' . $e->getMessage());
            $task->saveResult([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Run exchange rate update task
     */
    protected function runExchangeRateUpdate(): void
    {
        $task = SystemTask::getTask('update_exchange_rate', 21600); // 6 hours

        if (!$task->shouldRun()) {
            return;
        }

        $task->markAsRunning();

        try {
            $exchangeRateService = app(ExchangeRateService::class);
            $success = $exchangeRateService->updateRate('USD', 'EGP');

            $rate = $exchangeRateService->getRate('USD', 'EGP');

            $task->saveResult([
                'success' => $success,
                'rate' => $rate,
            ]);

            Log::info('Opportunistic exchange rate update completed', [
                'rate' => $rate,
            ]);
        } catch (\Exception $e) {
            Log::error('Exchange rate update failed: ' . $e->getMessage());
            $task->saveResult([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function runAttendanceThresholdCheck(): void
    {
        $task = SystemTask::getTask('check_attendance_thresholds', 86400);

        if (! $task->shouldRun()) {
            return;
        }

        $task->markAsRunning();

        try {
            $created = app(\App\Services\AttendanceThresholdService::class)->checkAllStudents();
            $task->saveResult(['success' => true, 'alerts_created' => $created]);
        } catch (\Exception $e) {
            Log::error('Attendance threshold check failed: '.$e->getMessage());
            $task->saveResult(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    protected function runLiveStreamAttendanceSync(): void
    {
        $task = SystemTask::getTask('sync_live_stream_attendances', 3600);

        if (! $task->shouldRun()) {
            return;
        }

        $task->markAsRunning();

        try {
            $synced = app(\App\Services\LiveStreamAttendanceSyncService::class)->syncAll();
            $task->saveResult(['success' => true, 'synced' => $synced]);
        } catch (\Exception $e) {
            Log::error('Live stream attendance sync failed: '.$e->getMessage());
            $task->saveResult(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    protected function runCloseDailyCoverage(): void
    {
        $endHour = (int) config('attendance.daily_coverage.school_day_end_hour', 16);
        if ((int) now()->format('H') < $endHour) {
            return;
        }

        $task = SystemTask::getTask('close_daily_coverage', 86400);

        if (! $task->shouldRun()) {
            return;
        }

        $task->markAsRunning();

        try {
            $report = app(\App\Services\DailyAbsenceCoverageService::class)->closeDay(today()->toDateString());
            $task->saveResult(['success' => true, 'report' => $report]);
        } catch (\Exception $e) {
            Log::error('Close daily coverage failed: '.$e->getMessage());
            $task->saveResult(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
