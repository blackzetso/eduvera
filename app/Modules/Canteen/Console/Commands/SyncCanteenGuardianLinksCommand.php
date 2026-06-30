<?php

namespace App\Modules\Canteen\Console\Commands;

use App\Models\User;
use App\Modules\Canteen\Models\StudentProfile;
use App\Modules\Canteen\Services\CanteenGuardianProfileSyncService;
use Illuminate\Console\Command;

class SyncCanteenGuardianLinksCommand extends Command
{
    protected $signature = 'canteen:sync-guardian-links
                            {--student= : Limit sync to a single student user id}
                            {--missing-only : Only profiles missing primary_guardian_user_id}';

    protected $description = 'Backfill canteen student profile guardian links from EDUVERA guardian_student data';

    public function handle(CanteenGuardianProfileSyncService $sync): int
    {
        $query = StudentProfile::query()
            ->whereNotNull('user_id')
            ->when($this->option('missing-only'), fn ($q) => $q->whereNull('primary_guardian_user_id'))
            ->when($this->option('student'), fn ($q, $id) => $q->where('user_id', (int) $id));

        $count = 0;

        $query->orderBy('student_name')->chunkById(100, function ($profiles) use ($sync, &$count) {
            foreach ($profiles as $profile) {
                $student = User::query()->students()->find($profile->user_id);

                if (! $student) {
                    continue;
                }

                $sync->syncForStudent($student, $profile);
                $count++;
            }
        });

        $this->info("Synced guardian links for {$count} canteen student profile(s).");

        return self::SUCCESS;
    }
}
