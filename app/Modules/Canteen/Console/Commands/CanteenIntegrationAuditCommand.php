<?php

namespace App\Modules\Canteen\Console\Commands;

use App\Models\User;
use App\Modules\Canteen\CanteenModule;
use App\Modules\Canteen\Events\CanteenSaleCompleted;
use App\Modules\Canteen\Events\CanteenSaleFailed;
use App\Modules\Canteen\Events\CanteenSaleVoided;
use App\Modules\Canteen\Integration\Contracts\FinanceIntegrationPort;
use App\Modules\Canteen\Integration\Contracts\GuardianIntegrationPort;
use App\Modules\Canteen\Integration\Contracts\ParentNotificationPort;
use App\Modules\Canteen\Integration\Contracts\StudentIdentityPort;
use App\Modules\Canteen\Integration\Contracts\WalletSettlementPort;
use App\Modules\Canteen\Listeners\DispatchCanteenAdminFailureNotifications;
use App\Modules\Canteen\Listeners\DispatchCanteenPurchaseNotifications;
use App\Modules\Canteen\Listeners\RecordCanteenFinanceEntry;
use App\Modules\Canteen\Models\CanteenFinanceEntry;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Models\StudentProfile;
use App\Modules\Canteen\Support\SaleStatus;
use App\Support\Student\StudentStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class CanteenIntegrationAuditCommand extends Command
{
    protected $signature = 'canteen:integration-audit {--json : Output machine-readable JSON}';

    protected $description = 'Audit canteen module wiring: adapters, tables, events, routes, and EDUVERA data readiness';

    /** @var list<string> */
    protected array $issues = [];

    /** @var list<string> */
    protected array $warnings = [];

    /** @var list<string> */
    protected array $ok = [];

    public function handle(): int
    {
        $this->auditModuleConfig();
        $this->auditAdapters();
        $this->auditDatabase();
        $this->auditEventListeners();
        $this->auditRoutes();
        $this->auditEduveraData();
        $this->auditIntegrationMode();

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => $this->ok,
                'warnings' => $this->warnings,
                'issues' => $this->issues,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $this->issues === [] ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->info('=== Canteen Integration Audit ===');
        $this->newLine();

        if ($this->ok !== []) {
            $this->line('<fg=green>OK</>');
            foreach ($this->ok as $line) {
                $this->line("  ✓ {$line}");
            }
            $this->newLine();
        }

        if ($this->warnings !== []) {
            $this->line('<fg=yellow>WARNINGS</>');
            foreach ($this->warnings as $line) {
                $this->line("  ! {$line}");
            }
            $this->newLine();
        }

        if ($this->issues !== []) {
            $this->line('<fg=red>ISSUES</>');
            foreach ($this->issues as $line) {
                $this->line("  ✗ {$line}");
            }
            $this->newLine();
        }

        if ($this->issues === []) {
            $this->info('All critical integration checks passed.');

            return self::SUCCESS;
        }

        $this->error(count($this->issues).' issue(s) require attention.');

        return self::FAILURE;
    }

    protected function auditModuleConfig(): void
    {
        if (CanteenModule::enabled()) {
            $this->pass('Module enabled (CANTEEN_ENABLED=true)');
        } else {
            $this->recordWarning('Module disabled — routes and POS UI hidden until CANTEEN_ENABLED=true');
        }
    }

    protected function auditAdapters(): void
    {
        $ports = [
            'student' => [StudentIdentityPort::class, 'CANTEEN_STUDENT_ADAPTER'],
            'wallet' => [WalletSettlementPort::class, 'CANTEEN_WALLET_ADAPTER'],
            'guardian' => [GuardianIntegrationPort::class, 'CANTEEN_GUARDIAN_ADAPTER'],
            'parent' => [ParentNotificationPort::class, 'CANTEEN_PARENT_ADAPTER'],
            'finance' => [FinanceIntegrationPort::class, 'CANTEEN_FINANCE_ADAPTER'],
        ];

        foreach ($ports as $group => [$contract, $envKey]) {
            $configKey = match ($group) {
                'student' => 'student_adapter',
                'wallet' => 'wallet_adapter',
                'guardian' => 'guardian_adapter',
                'parent' => 'parent_adapter',
                'finance' => 'finance_adapter',
            };

            $key = config("canteen.integration.{$configKey}");
            $class = config("canteen.adapters.{$group}.{$key}");

            try {
                $resolved = app($contract);
                $this->pass("{$group} adapter [{$key}] → ".class_basename($resolved::class)." ({$envKey})");
            } catch (\Throwable $e) {
                $this->recordIssue("{$group} adapter [{$key}] failed: {$e->getMessage()}");
            }

            if (! is_string($class) || ! class_exists($class)) {
                $this->recordIssue("Adapter class missing for {$group}={$key}");
            }
        }
    }

    protected function auditDatabase(): void
    {
        $tables = [
            'canteen_settings',
            'canteen_staff',
            'canteen_categories',
            'canteen_products',
            'canteen_student_profiles',
            'canteen_sales',
            'canteen_sale_items',
            'canteen_inventory_transactions',
            'canteen_wallet_ready_transactions',
            'canteen_parent_visibility_queue',
            'canteen_finance_entries',
            'canteen_student_blocked_products',
            'canteen_student_blocked_categories',
            'guardian_student',
            'guardian_notification_preferences',
            'user_wallets',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $this->pass("Table exists: {$table}");
            } else {
                $this->recordIssue("Missing table: {$table} — run php artisan migrate");
            }
        }
    }

    protected function auditEventListeners(): void
    {
        $expected = [
            CanteenSaleCompleted::class => [
                'count' => 2,
                'listeners' => [
                    RecordCanteenFinanceEntry::class,
                    DispatchCanteenPurchaseNotifications::class,
                ],
            ],
            CanteenSaleFailed::class => [
                'count' => 2,
                'listeners' => [
                    RecordCanteenFinanceEntry::class,
                    DispatchCanteenAdminFailureNotifications::class,
                ],
            ],
            CanteenSaleVoided::class => [
                'count' => 1,
                'listeners' => [
                    RecordCanteenFinanceEntry::class,
                ],
            ],
        ];

        foreach ($expected as $event => $config) {
            if (! Event::hasListeners($event)) {
                $this->recordIssue('No listeners registered for '.class_basename($event));

                continue;
            }

            $registered = $this->rawEventListeners($event);

            if (count($registered) < $config['count']) {
                $this->recordIssue(class_basename($event).' expects '.$config['count'].' listener(s), found '.count($registered));

                continue;
            }

            $serialized = collect($registered)
                ->map(fn ($entry) => $this->describeListener($entry))
                ->implode(' ');

            foreach ($config['listeners'] as $listener) {
                if (str_contains($serialized, class_basename($listener))) {
                    $this->pass('Event listener: '.class_basename($event).' → '.class_basename($listener));
                } else {
                    $this->recordIssue('Missing event listener: '.class_basename($event).' → '.class_basename($listener));
                }
            }
        }
    }

    /**
     * @return list<mixed>
     */
    protected function rawEventListeners(string $event): array
    {
        $dispatcher = Event::getFacadeRoot();
        $reflection = new \ReflectionClass($dispatcher);
        $property = $reflection->getProperty('listeners');
        $property->setAccessible(true);

        /** @var array<string, list<mixed>> $listeners */
        $listeners = $property->getValue($dispatcher);

        return $listeners[$event] ?? [];
    }

    protected function describeListener(mixed $entry): string
    {
        if (is_string($entry)) {
            return $entry;
        }

        if (is_array($entry)) {
            return collect($entry)
                ->map(fn ($part) => is_object($part) ? $part::class : (is_string($part) ? $part : ''))
                ->implode('@');
        }

        if (is_object($entry)) {
            return $entry::class;
        }

        return '';
    }

    protected function auditRoutes(): void
    {
        $routeNames = [
            'canteen.dashboard',
            'canteen.api.sales.store',
            'guardian.canteen.api.summary',
            'guardian.canteen.api.children.limits.show',
        ];

        foreach ($routeNames as $name) {
            if (Route::has($name)) {
                $this->pass("Route registered: {$name}");
            } elseif (! CanteenModule::enabled()) {
                $this->recordWarning("Route not loaded (module disabled): {$name}");
            } else {
                $this->recordIssue("Route missing: {$name}");
            }
        }
    }

    protected function auditEduveraData(): void
    {
        $activeStudents = User::query()
            ->where('user_type', 'student')
            ->where('student_status', StudentStatus::ACTIVE)
            ->count();

        $enrolledStudents = User::query()
            ->where('user_type', 'student')
            ->where('student_status', StudentStatus::ACTIVE)
            ->whereHas('currentStudentEnrollment')
            ->count();

        $withWallet = User::query()
            ->where('user_type', 'student')
            ->where('student_status', StudentStatus::ACTIVE)
            ->whereHas('currentStudentEnrollment')
            ->whereHas('wallet')
            ->count();

        $withCode = User::query()
            ->where('user_type', 'student')
            ->where('student_status', StudentStatus::ACTIVE)
            ->whereHas('currentStudentEnrollment')
            ->whereNotNull('student_code')
            ->where('student_code', '!=', '')
            ->count();

        $profiles = StudentProfile::query()->whereNotNull('user_id')->count();
        $profilesWithGuardian = StudentProfile::query()->whereNotNull('primary_guardian_user_id')->count();

        $this->pass("Active students: {$activeStudents}");
        $this->pass("Enrolled (current): {$enrolledStudents}");
        $this->pass("POS-ready (enrolled + wallet): {$withWallet}");
        $this->pass("With student_code: {$withCode}");
        $this->pass("Canteen profiles synced: {$profiles}");
        $this->pass("Profiles with guardian link: {$profilesWithGuardian}");

        if ($enrolledStudents > 0 && $withWallet < $enrolledStudents) {
            $missing = $enrolledStudents - $withWallet;
            $this->recordWarning("{$missing} enrolled student(s) without wallet — run wallet setup or students:repair-for-canteen");
        }

        if ($enrolledStudents > 0 && $profiles < $enrolledStudents) {
            $this->recordWarning('Some students lack canteen profiles — run: php artisan students:repair-for-canteen');
        }

        if ($profiles > 0 && $profilesWithGuardian < $profiles) {
            $missing = $profiles - $profilesWithGuardian;
            $this->recordWarning("{$missing} profile(s) missing guardian — run: php artisan canteen:sync-guardian-links --missing-only");
        }

        $completedSales = Sale::query()->where('status', SaleStatus::COMPLETED)->count();
        $financeEntries = CanteenFinanceEntry::query()->count();

        $this->pass("Completed sales: {$completedSales}");
        $this->pass("Finance ledger entries: {$financeEntries}");

        if (config('canteen.integration.finance_adapter') === 'eduvera' && $completedSales > 0 && $financeEntries === 0) {
            $this->recordWarning('Finance adapter is eduvera but no ledger entries — run: php artisan canteen:reconcile-finance');
        }
    }

    protected function auditIntegrationMode(): void
    {
        $student = config('canteen.integration.student_adapter');
        $wallet = config('canteen.integration.wallet_adapter');
        $guardian = config('canteen.integration.guardian_adapter');
        $parent = config('canteen.integration.parent_adapter');
        $finance = config('canteen.integration.finance_adapter');

        $fullEduvera = $student === 'core'
            && $wallet === 'user_wallet'
            && $guardian === 'core'
            && $parent === 'eduvera'
            && $finance === 'eduvera';

        if ($fullEduvera) {
            $this->pass('Full EDUVERA integration mode active (all adapters on core/eduvera/user_wallet)');

            return;
        }

        $this->recordWarning('Partial integration — current adapters: '
            ."student={$student}, wallet={$wallet}, guardian={$guardian}, parent={$parent}, finance={$finance}");

        if ($wallet === 'pending') {
            $this->recordWarning('Wallet adapter is pending — checkout completes but does not debit UserWallet');
        }

        if ($parent === 'queued') {
            $this->recordWarning('Parent adapter is queued — notifications stay in canteen_parent_visibility_queue until published');
        }

        if ($finance === 'noop') {
            $this->recordWarning('Finance adapter is noop — no canteen_finance_entries written on sale events');
        }

        $this->recordWarning('For full EDUVERA integration set: CANTEEN_STUDENT_ADAPTER=core, CANTEEN_WALLET_ADAPTER=user_wallet, CANTEEN_PARENT_ADAPTER=eduvera, CANTEEN_FINANCE_ADAPTER=eduvera');
    }

    protected function pass(string $message): void
    {
        $this->ok[] = $message;
    }

    protected function recordWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    protected function recordIssue(string $message): void
    {
        $this->issues[] = $message;
    }
}
