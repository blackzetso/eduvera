<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WalletService;
use Illuminate\Support\Facades\Log;

class SyncBunnyConsumption extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bunny:sync-consumption';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync storage and bandwidth consumption from Bunny CDN';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Bunny consumption sync...');

        try {
            $walletService = app(WalletService::class);
            $result = $walletService->syncConsumptionFromBunny();

            if ($result['success']) {
                if (isset($result['no_sync_needed']) && $result['no_sync_needed']) {
                    $this->info("ℹ Already synced today");
                    $this->line("  Last sync: {$result['last_synced_at']}");
                } else {
                    $this->info("✓ Synced successfully");
                    $this->line("  New Storage: {$result['new_storage_gb']} GB → \${$result['storage_cost']}");
                    $this->line("  New Bandwidth: {$result['new_bandwidth_gb']} GB → \${$result['bandwidth_cost']}");
                    $this->line("  Total deducted: \${$result['total_cost']}");
                }
            } else {
                $this->warn("✗ Sync failed: {$result['message']}");
            }
        } catch (\Exception $e) {
            $this->error("✗ Error syncing: {$e->getMessage()}");
            Log::error("Bunny sync error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }

        $this->info('Sync completed!');
        return 0;
    }
}
