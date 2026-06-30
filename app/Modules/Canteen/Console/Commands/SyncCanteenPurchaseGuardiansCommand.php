<?php

namespace App\Modules\Canteen\Console\Commands;

use App\Modules\Canteen\Services\CanteenPurchaseGuardianSyncService;
use Illuminate\Console\Command;

class SyncCanteenPurchaseGuardiansCommand extends Command
{
    protected $signature = 'canteen:sync-purchase-guardians
                            {--chunk=200 : Number of sales processed per chunk}';

    protected $description = 'Backfill primary guardian references on canteen sales and parent visibility queue rows';

    public function handle(CanteenPurchaseGuardianSyncService $sync): int
    {
        $updated = $sync->syncAllMissing((int) $this->option('chunk'));

        $this->info("Updated guardian linkage on {$updated} sale(s).");

        return self::SUCCESS;
    }
}
