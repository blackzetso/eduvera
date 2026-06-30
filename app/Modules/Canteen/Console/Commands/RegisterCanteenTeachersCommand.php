<?php

namespace App\Modules\Canteen\Console\Commands;

use App\Modules\Canteen\CanteenModule;
use App\Modules\Canteen\Services\CanteenStaffRegistrationService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class RegisterCanteenTeachersCommand extends Command
{
    protected $signature = 'canteen:register-teachers
                            {--role=cashier : Default role when no individual mapping exists (manager|cashier)}
                            {--staff=* : Role overrides as identifier:role (user id or email)}
                            {--dry-run : Show planned changes without writing}';

    protected $description = 'Register all teachers in canteen_staff with configurable roles (idempotent)';

    public function handle(CanteenStaffRegistrationService $registration): int
    {
        if (! CanteenModule::enabled()) {
            $this->error('Canteen module is disabled (CANTEEN_ENABLED=false).');

            return self::FAILURE;
        }

        try {
            $stats = $registration->registerAllTeachers([
                'default_role' => $this->option('role'),
                'role_specs' => $this->option('staff'),
                'dry_run' => (bool) $this->option('dry-run'),
            ]);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Teachers found: '.count($stats['teachers']));

        if ($stats['teachers'] !== []) {
            $this->table(
                ['ID', 'Name', 'Email', 'Assigned role'],
                collect($stats['teachers'])->map(fn (array $teacher) => [
                    $teacher['id'],
                    $teacher['name'],
                    $teacher['email'],
                    $teacher['role'],
                ])->all(),
            );
        }

        $this->newLine();
        $this->info('Canteen teacher registration '.($this->option('dry-run') ? '(dry run) ' : '').'summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Newly registered', $stats['registered']],
                ['Existing records updated', $stats['updated']],
                ['Unchanged', $stats['unchanged']],
                ['Skipped (errors)', count($stats['skipped'])],
            ],
        );

        foreach ($stats['skipped'] as $skipped) {
            $this->warn("Skipped #{$skipped['id']} {$skipped['name']}: {$skipped['reason']}");
        }

        return $stats['skipped'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
