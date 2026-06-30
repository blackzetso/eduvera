<?php

namespace App\Modules\Canteen\Console\Commands;

use App\Modules\Canteen\Services\CanteenSyncAllService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class CanteenFullSyncCommand extends Command
{
    protected $signature = 'canteen:full-sync
                            {--staff=* : Staff entries as identifier:role (user id or email)}
                            {--dry-run : Preview counts without writing}
                            {--skip-staff : Skip canteen_staff registration}
                            {--skip-guardians : Skip guardian link and purchase guardian sync}
                            {--all-guardians : Sync all profiles, not only missing primary_guardian_user_id}';

    protected $description = 'Alias of canteen:sync-all (backward compatible)';

    public function handle(CanteenSyncAllService $sync): int
    {
        $this->warn('canteen:full-sync is deprecated — use canteen:sync-all instead.');

        try {
            $stats = $sync->run([
                'dry_run' => (bool) $this->option('dry-run'),
                'skip_staff' => (bool) $this->option('skip-staff'),
                'skip_guardians' => (bool) $this->option('skip-guardians'),
                'guardian_missing_only' => ! (bool) $this->option('all-guardians'),
                'default_role' => config('canteen.teacher_staff.default_role', 'cashier'),
                'role_specs' => $sync->mergeRoleSpecs($this->option('staff')),
            ]);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Canteen full sync '.($this->option('dry-run') ? '(dry run) ' : '').'completed.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['New student profiles created', $stats['profiles_created']],
                ['Existing student profiles updated', $stats['profiles_updated']],
                ['Staff newly registered', $stats['staff_registered']],
                ['Staff records updated', $stats['staff_updated']],
                ['Guardian links synced', $stats['guardians_synced']],
                ['Sale purchase guardians synced', $stats['purchase_guardians_synced']],
                ['Health/allergy records updated', $stats['health_records_updated']],
            ],
        );

        $this->line('Adapters: student='.config('canteen.integration.student_adapter')
            .', guardian='.config('canteen.integration.guardian_adapter'));

        return self::SUCCESS;
    }
}
