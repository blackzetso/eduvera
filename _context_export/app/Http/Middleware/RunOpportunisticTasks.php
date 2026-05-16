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
        // Run tasks after sending response to user (non-blocking)
        $response = $next($request);

        // Run tasks in background (after response is sent)
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        $this->runPendingTasks();

        return $response;
    }

    /**
     * Run any pending tasks
     */
    protected function runPendingTasks(): void
    {
        try {
            // Task 1: Sync consumption (daily)
            $this->runConsumptionSync();

            // Task 2: Update exchange rate (every 6 hours)
            $this->runExchangeRateUpdate();

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
}
