<?php

namespace App\Modules\Canteen\Console\Commands;

use App\Jobs\Canteen\NotifyGuardiansOfCanteenPurchase;
use App\Modules\Canteen\Models\ParentVisibilityQueue;
use Illuminate\Console\Command;

class PublishCanteenPendingNotificationsCommand extends Command
{
    protected $signature = 'canteen:publish-pending-notifications
                            {--limit=100 : Maximum queue rows to process}';

    protected $description = 'Dispatch guardian notifications for pending canteen purchase visibility queue rows';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $rows = ParentVisibilityQueue::query()
            ->where('visibility_status', 'pending')
            ->whereIn('notification_status', ['none', 'pending', 'failed'])
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No pending canteen notifications.');

            return self::SUCCESS;
        }

        foreach ($rows as $row) {
            NotifyGuardiansOfCanteenPurchase::dispatch($row->sale_id);
            $this->line("Queued notification job for sale {$row->sale_id}");
        }

        $this->info("Dispatched {$rows->count()} notification job(s).");

        return self::SUCCESS;
    }
}
