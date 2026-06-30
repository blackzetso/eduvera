<?php

namespace App\Console\Commands;

use App\Jobs\CheckKnowledgeReviewStatusJob;
use Illuminate\Console\Command;

class CheckKnowledgeReviewStatus extends Command
{
    protected $signature = 'dova:check-knowledge-review';

    protected $description = 'Mark overdue FAQs for review and send owner reminders';

    public function handle(): int
    {
        CheckKnowledgeReviewStatusJob::dispatchSync();

        $this->info('Knowledge review status check completed.');

        return self::SUCCESS;
    }
}
