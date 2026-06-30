<?php

namespace App\Console\Commands;

use App\Services\Admission\AdmissionEngagementBackfillService;
use Illuminate\Console\Command;

class BackfillAdmissionEngagements extends Command
{
    protected $signature = 'admissions:backfill-engagements';

    protected $description = 'Create admission engagements from existing visits, notes, and website intake records';

    public function handle(AdmissionEngagementBackfillService $backfill): int
    {
        $this->info('Backfilling admission engagements…');

        $counts = $backfill->run();

        foreach ($counts as $key => $count) {
            $this->line("  {$key}: {$count}");
        }

        $this->info('Backfill complete.');

        return self::SUCCESS;
    }
}
