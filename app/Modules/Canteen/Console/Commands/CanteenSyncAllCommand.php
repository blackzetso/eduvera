<?php

namespace App\Modules\Canteen\Console\Commands;

use App\Modules\Canteen\Services\CanteenSyncAllService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class CanteenSyncAllCommand extends Command
{
    protected $signature = 'canteen:sync-all
                            {--manager-user-id= : Canteen POS manager (users.id, must be user_type=teacher)}
                            {--manager-email= : Canteen POS manager by email (must be a teacher)}
                            {--staff=* : Staff role overrides as identifier:role (user id or email)}
                            {--role=cashier : Default canteen_staff role for teachers (manager|cashier)}
                            {--dry-run : Preview actions without modifying the database}
                            {--skip-staff : Skip teacher registration in canteen_staff}
                            {--skip-guardians : Skip guardian profile and sale guardian sync}
                            {--all-guardians : Sync all guardian links, not only missing primary_guardian_user_id}';

    protected $description = 'Full canteen system sync: student profiles, staff, guardians, and health restrictions';

    public function handle(CanteenSyncAllService $sync): int
    {
        try {
            $stats = $sync->run([
                'dry_run' => (bool) $this->option('dry-run'),
                'skip_staff' => (bool) $this->option('skip-staff'),
                'skip_guardians' => (bool) $this->option('skip-guardians'),
                'guardian_missing_only' => ! (bool) $this->option('all-guardians'),
                'default_role' => (string) $this->option('role'),
                'role_specs' => $sync->mergeRoleSpecs($this->option('staff')),
                'manager_user_id' => $this->option('manager-user-id'),
                'manager_email' => $this->option('manager-email'),
            ]);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->renderReport($stats);

        if ($stats['has_gaps']) {
            $this->newLine();
            $this->warn('Sync completed with '.count($stats['gaps']).' gap(s) — exit code 1.');
            foreach ($stats['gaps'] as $gap) {
                $this->line('  • '.$gap);
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Sync completed with no gaps — exit code 0.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    protected function renderReport(array $stats): void
    {
        $this->newLine();
        $this->info('Canteen sync-all '.($this->option('dry-run') ? '(dry run) ' : '').'completed.');

        $this->newLine();
        $this->comment('Students');
        $this->table(
            ['Metric', 'Count'],
            [
                ['New profiles created', $stats['profiles_created']],
                ['Existing profiles updated', $stats['profiles_updated']],
            ],
        );

        if (! $this->option('skip-staff')) {
            $this->newLine();
            $this->comment('Staff / Teachers');

            $managerLine = $stats['manager_user_id']
                ? "Manager: user #{$stats['manager_user_id']} (source: {$stats['manager_source']})"
                : 'Manager: not configured — all teachers use default role ('.$this->option('role').')';

            $this->line($managerLine);

            $this->table(
                ['Metric', 'Count'],
                [
                    ['Staff newly registered', $stats['staff_registered']],
                    ['Staff records updated', $stats['staff_updated']],
                    ['Staff unchanged', $stats['staff_unchanged']],
                ],
            );

            if (! empty($stats['staff_teachers'])) {
                $this->table(
                    ['ID', 'Name', 'Email', 'Role', 'Outcome'],
                    collect($stats['staff_teachers'])->map(fn (array $row) => [
                        $row['id'],
                        $row['name'],
                        $row['email'],
                        $row['role'],
                        $row['outcome'] ?? '—',
                    ])->all(),
                );
            }

            if (! empty($stats['staff_skipped'])) {
                $this->warn('Skipped staff registrations:');
                $this->table(
                    ['ID', 'Name', 'Reason'],
                    collect($stats['staff_skipped'])->map(fn (array $row) => [
                        $row['id'],
                        $row['name'],
                        $row['reason'],
                    ])->all(),
                );
            }
        }

        if (! $this->option('skip-guardians')) {
            $this->newLine();
            $this->comment('Guardians');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Guardian links synced', $stats['guardians_synced']],
                    ['Sale purchase guardians synced', $stats['purchase_guardians_synced']],
                    ['Students still missing guardian', count($stats['missing_guardians'] ?? [])],
                ],
            );

            if (! empty($stats['missing_guardians'])) {
                $this->warn('Students without primary guardian (link in admin → guardian_student):');
                $this->table(
                    ['User ID', 'Student ref', 'Name'],
                    collect($stats['missing_guardians'])->take(20)->map(fn (array $row) => [
                        $row['user_id'],
                        $row['student_id_ref'],
                        $row['name'],
                    ])->all(),
                );

                if (count($stats['missing_guardians']) > 20) {
                    $this->line('… and '.(count($stats['missing_guardians']) - 20).' more.');
                }
            }
        } else {
            $missingCount = count($stats['missing_guardians'] ?? []);

            if ($missingCount > 0) {
                $this->newLine();
                $this->comment('Guardians (skipped sync)');
                $this->line("Students still missing guardian: {$missingCount}");
            }
        }

        $this->newLine();
        $this->comment('Health / restrictions');

        $healthRows = collect($stats['health_summaries'] ?? []);
        $withAllergies = $healthRows->where('allergies_count', '>', 0)->count();
        $withBlockedTags = $healthRows->where('blocked_tags_count', '>', 0)->count();
        $blockAll = $healthRows->where('block_all_purchases', true)->count();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Profiles normalized this run', $stats['health_records_updated']],
                ['Students with allergies', $withAllergies],
                ['Students with blocked tags', $withBlockedTags],
                ['Students with block-all purchases', $blockAll],
                ['Total active student profiles', $healthRows->count()],
            ],
        );

        if ($healthRows->isNotEmpty()) {
            $this->table(
                ['User ID', 'Name', 'Allergies', 'Blocked tags', 'Block all'],
                $healthRows->take(50)->map(fn (array $row) => [
                    $row['user_id'],
                    $row['name'],
                    $row['allergies_count'],
                    $row['blocked_tags_count'],
                    $row['block_all_purchases'] ? 'yes' : 'no',
                ])->all(),
            );

            if ($healthRows->count() > 50) {
                $this->line('… and '.($healthRows->count() - 50).' more student health rows.');
            }
        }

        if (! empty($stats['missing_profiles'])) {
            $this->newLine();
            $this->warn('Active enrolled students still missing canteen profiles:');
            $this->table(
                ['User ID', 'Name', 'Student code'],
                collect($stats['missing_profiles'])->take(20)->map(fn (array $row) => [
                    $row['user_id'],
                    $row['name'],
                    $row['student_code'],
                ])->all(),
            );
        }

        $this->newLine();
        $this->line('Adapters: student='.config('canteen.integration.student_adapter')
            .', guardian='.config('canteen.integration.guardian_adapter')
            .', wallet='.config('canteen.integration.wallet_adapter'));
    }
}
