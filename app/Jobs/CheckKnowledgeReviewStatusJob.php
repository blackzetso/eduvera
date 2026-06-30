<?php

namespace App\Jobs;

use App\Services\Dova\DovaFaqGovernanceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckKnowledgeReviewStatusJob implements ShouldQueue
{
    use Queueable;

    public function handle(DovaFaqGovernanceService $governance): void
    {
        $governance->markOverdueFaqs();
        $governance->sendReviewReminders();
    }
}
