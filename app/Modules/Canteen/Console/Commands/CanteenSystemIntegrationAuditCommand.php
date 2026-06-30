<?php

namespace App\Modules\Canteen\Console\Commands;

use App\Models\User;
use App\Modules\Canteen\CanteenModule;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Models\Staff;
use App\Modules\Canteen\Models\StudentProfile;
use App\Modules\Canteen\Models\WalletReadyTransaction;
use App\Modules\Canteen\Support\CanteenPermission;
use App\Modules\Canteen\Support\SaleStatus;
use App\Support\Student\StudentStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class CanteenSystemIntegrationAuditCommand extends Command
{
    protected $signature = 'canteen:system-audit
                            {--json : Output JSON report}
                            {--limit=20 : Max rows per issue list}';

    protected $description = 'Full EDUVERA canteen system integration audit (students, staff, guardians, wallet, sales, APIs)';

    /** @var array<string, mixed> */
    protected array $report = [];

    public function handle(): int
    {
        $this->report = [
            'generated_at' => now()->toIso8601String(),
            'module_enabled' => CanteenModule::enabled(),
            'adapters' => config('canteen.integration'),
            'sections' => [],
            'issues' => [],
            'warnings' => [],
            'recommendations' => [],
        ];

        $this->auditNewStudentFlow();
        $this->auditExistingStudents();
        $this->auditStaffAndAccess();
        $this->auditGuardianIntegration();
        $this->auditWalletAndCheckout();
        $this->auditSalesAndInventory();
        $this->auditApiRoutes();
        $this->auditNotificationsFinance();
        $this->buildRecommendations();

        if ($this->option('json')) {
            $this->line(json_encode($this->report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return empty($this->report['issues']) ? self::SUCCESS : self::FAILURE;
        }

        $this->renderReport();

        return empty($this->report['issues']) ? self::SUCCESS : self::FAILURE;
    }

    protected function auditNewStudentFlow(): void
    {
        $section = ['title' => '1. New Students — Auto-link & POS readiness'];

        $activeEligible = User::query()
            ->where('user_type', 'student')
            ->where('student_status', StudentStatus::ACTIVE)
            ->whereHas('currentStudentEnrollment')
            ->count();

        $withProfile = $this->activeEnrolledStudentsWithProfileCount();

        $autoSyncOnCreate = $this->detectAutoSyncOnUserCreate();

        $section['auto_sync_on_user_create'] = $autoSyncOnCreate;
        $section['lazy_sync_via_pos'] = config('canteen.integration.student_adapter') === 'core';
        $section['active_enrolled_students'] = $activeEligible;
        $section['with_canteen_profile'] = $withProfile;
        $section['missing_profile'] = $activeEligible - $withProfile;

        if (! $autoSyncOnCreate) {
            $this->addWarning('New students are NOT auto-linked on User creation — profiles created lazily at POS search/checkout (core adapter) or via students:repair-for-canteen');
        }

        if (config('canteen.integration.student_adapter') === 'local') {
            $this->addIssue('CANTEEN_STUDENT_ADAPTER=local — POS cannot discover new students from users table until a profile row exists manually');
        }

        $missing = $this->studentsMissingProfile($this->option('limit'));
        $section['sample_missing_profiles'] = $missing;

        if ($missing !== []) {
            $this->addWarning(count($missing).' active enrolled student(s) lack canteen_student_profiles (first POS search with core adapter will create them)');
        }

        $this->report['sections']['new_students'] = $section;
    }

    protected function auditExistingStudents(): void
    {
        $section = ['title' => '2. Existing Students — Consistency'];

        $active = User::query()
            ->where('user_type', 'student')
            ->where('student_status', StudentStatus::ACTIVE)
            ->count();

        $enrolled = User::query()
            ->where('user_type', 'student')
            ->where('student_status', StudentStatus::ACTIVE)
            ->whereHas('currentStudentEnrollment')
            ->count();

        $noEnrollment = User::query()
            ->where('user_type', 'student')
            ->where('student_status', StudentStatus::ACTIVE)
            ->whereDoesntHave('currentStudentEnrollment')
            ->count();

        $noWallet = User::query()
            ->where('user_type', 'student')
            ->where('student_status', StudentStatus::ACTIVE)
            ->whereHas('currentStudentEnrollment')
            ->whereDoesntHave('wallet')
            ->count();

        $noCode = User::query()
            ->where('user_type', 'student')
            ->where('student_status', StudentStatus::ACTIVE)
            ->where(fn ($q) => $q->whereNull('student_code')->orWhere('student_code', ''))
            ->count();

        $inactiveProfiles = StudentProfile::query()
            ->where('is_active', false)
            ->whereNotNull('user_id')
            ->count();

        $orphanProfiles = StudentProfile::query()
            ->whereNull('user_id')
            ->count();

        $mismatches = $this->profileEnrollmentMismatches($this->option('limit'));

        $section['active_students'] = $active;
        $section['enrolled_active'] = $enrolled;
        $section['active_without_enrollment'] = $noEnrollment;
        $section['enrolled_without_wallet'] = $noWallet;
        $section['active_without_student_code'] = $noCode;
        $section['inactive_canteen_profiles'] = $inactiveProfiles;
        $section['orphan_profiles_no_user_id'] = $orphanProfiles;
        $section['profile_enrollment_mismatches'] = $mismatches;

        if ($noEnrollment > 0) {
            $this->addIssue("{$noEnrollment} active student(s) without current enrollment — invisible in POS");
            $section['sample_no_enrollment'] = $this->sampleStudentsWithoutEnrollment($this->option('limit'));
        }

        if ($noWallet > 0) {
            $this->addWarning("{$noWallet} enrolled student(s) without user_wallets — checkout fails with user_wallet adapter");
        }

        if ($noCode > 0) {
            $this->addWarning("{$noCode} active student(s) without student_code — POS search by code unavailable");
        }

        if ($mismatches !== []) {
            $this->addWarning(count($mismatches).' profile(s) have grade/class differing from current enrollment');
        }

        $this->report['sections']['existing_students'] = $section;
    }

    protected function auditStaffAndAccess(): void
    {
        $section = ['title' => '3. Teachers & Staff — Canteen access'];

        $staffRows = Staff::query()->where('is_active', true)->with('user:id,name,email,user_type')->get();
        $section['active_canteen_staff'] = $staffRows->count();
        $section['staff_by_role'] = $staffRows->groupBy('role')->map->count()->all();
        $section['staff_list'] = $staffRows->map(fn (Staff $s) => [
            'user_id' => $s->user_id,
            'name' => $s->user?->name,
            'user_type' => $s->user?->user_type,
            'role' => $s->role,
        ])->values()->all();

        $teachers = User::query()->where('user_type', 'teacher')->count();
        $teachersWithStaff = User::query()
            ->where('user_type', 'teacher')
            ->whereIn('id', $staffRows->pluck('user_id'))
            ->count();

        $section['total_teachers'] = $teachers;
        $section['teachers_with_canteen_staff'] = $teachersWithStaff;

        $admins = User::query()->where('user_type', 'admin')->count();
        $section['admin_users_with_full_access'] = $admins;

        $permissionChecks = [];
        foreach (['manager', 'cashier'] as $role) {
            $perms = config("canteen.roles.{$role}", []);
            $permissionChecks[$role] = [
                'pos_access' => in_array('canteen.pos.access', $perms, true),
                'void_transactions' => in_array('canteen.transactions.void', $perms, true),
                'manage_staff' => in_array('canteen.staff.manage', $perms, true),
            ];
        }
        $section['role_permission_matrix'] = $permissionChecks;

        if ($staffRows->isEmpty()) {
            $this->addWarning('No active canteen_staff rows — only admin users can access canteen until staff are assigned via Settings');
        }

        if ($teachers > 0 && $teachersWithStaff === 0) {
            $this->addWarning("{$teachers} teacher account(s) exist but none are registered in canteen_staff — assign via POST /canteen/settings/staff");
        }

        $this->report['sections']['staff_access'] = $section;
    }

    protected function auditGuardianIntegration(): void
    {
        $section = ['title' => '4. Guardian Integration'];

        $profiles = StudentProfile::query()->whereNotNull('user_id')->count();
        $withGuardian = StudentProfile::query()->whereNotNull('primary_guardian_user_id')->count();
        $withoutGuardian = $profiles - $withGuardian;

        $studentsWithPivot = DB::table('guardian_student')
            ->distinct('student_id')
            ->count('student_id');

        $missingLink = $this->studentsWithPivotButNoProfileGuardian($this->option('limit'));

        $blockedPurchases = StudentProfile::query()
            ->whereNotNull('user_id')
            ->where('metadata->guardian_purchase_blocked', true)
            ->count();

        $healthBlocked = StudentProfile::query()
            ->whereNotNull('user_id')
            ->where('health_restrictions->block_all_purchases', true)
            ->count();

        $productBlocks = Schema::hasTable('canteen_student_blocked_products')
            ? DB::table('canteen_student_blocked_products')->distinct('student_id_ref')->count('student_id_ref')
            : 0;

        $section['profiles_with_user'] = $profiles;
        $section['profiles_with_primary_guardian'] = $withGuardian;
        $section['profiles_missing_guardian'] = $withoutGuardian;
        $section['students_with_guardian_student_pivot'] = $studentsWithPivot;
        $section['guardian_purchase_blocked_count'] = $blockedPurchases;
        $section['health_block_all_count'] = $healthBlocked;
        $section['students_with_product_blocks'] = $productBlocks;
        $section['pivot_without_profile_guardian'] = $missingLink;

        if ($withoutGuardian > 0) {
            $this->addWarning("{$withoutGuardian} student profile(s) missing primary_guardian_user_id — run canteen:sync-guardian-links --missing-only");
            $section['sample_no_guardian'] = $this->studentsWithoutGuardianProfile($this->option('limit'));
        }

        if ($missingLink !== []) {
            $this->addWarning(count($missingLink).' student(s) have guardian_student pivot but profile primary_guardian is null');
        }

        $this->report['sections']['guardian'] = $section;
    }

    protected function auditWalletAndCheckout(): void
    {
        $section = ['title' => '5. Wallet & Checkout'];

        $walletAdapter = config('canteen.integration.wallet_adapter');
        $section['wallet_adapter'] = $walletAdapter;

        $completed = Sale::query()->where('status', SaleStatus::COMPLETED)->count();
        $failed = Sale::query()->where('status', SaleStatus::FAILED)->count();
        $pending = Sale::query()->where('status', SaleStatus::PENDING_PAYMENT)->count();
        $voided = Sale::query()->where('status', SaleStatus::VOIDED)->count();

        $section['sales_completed'] = $completed;
        $section['sales_failed'] = $failed;
        $section['sales_pending_payment'] = $pending;
        $section['sales_voided'] = $voided;

        $walletTx = WalletReadyTransaction::query()->count();
        $postedWallet = WalletReadyTransaction::query()->where('status', 'posted')->count();
        $section['wallet_ready_transactions'] = $walletTx;
        $section['wallet_ready_posted'] = $postedWallet;

        $completedNoWalletTx = Sale::query()
            ->where('status', SaleStatus::COMPLETED)
            ->whereDoesntHave('walletReadyTransaction')
            ->count();

        $section['completed_sales_without_wallet_tx'] = $completedNoWalletTx;

        if ($walletAdapter === 'pending' && $completed > 0) {
            $this->addWarning("Wallet adapter is pending — {$completed} completed sale(s) may not have debited user_wallets (by design)");
        }

        if ($walletAdapter === 'user_wallet' && $completedNoWalletTx > 0) {
            $this->addIssue("{$completedNoWalletTx} completed sale(s) missing canteen_wallet_ready_transactions");
        }

        $failedSample = Sale::query()
            ->where('status', SaleStatus::FAILED)
            ->orderByDesc('sold_at')
            ->limit((int) $this->option('limit'))
            ->get(['id', 'sale_number', 'student_id_ref', 'total', 'sold_at', 'metadata'])
            ->map(fn (Sale $s) => [
                'sale_number' => $s->sale_number,
                'student_ref' => $s->student_id_ref,
                'total' => $s->total,
                'reason' => $s->metadata['wallet_settlement_failure'] ?? $s->metadata['failure_reason'] ?? 'unknown',
                'sold_at' => $s->sold_at?->toDateTimeString(),
            ])
            ->all();

        $section['recent_failed_sales'] = $failedSample;

        $this->report['sections']['wallet_checkout'] = $section;
    }

    protected function auditSalesAndInventory(): void
    {
        $section = ['title' => '6. Sales & Inventory'];

        $completedNoTimestamp = Sale::query()
            ->where('status', SaleStatus::COMPLETED)
            ->whereNull('completed_at')
            ->count();

        $section['completed_without_completed_at'] = $completedNoTimestamp;

        if ($completedNoTimestamp > 0) {
            $this->addIssue("{$completedNoTimestamp} completed sale(s) missing completed_at timestamp");
        }

        $salesWithInventory = DB::table('canteen_inventory_transactions')
            ->where('reference_type', 'sale')
            ->distinct('reference_id')
            ->count('reference_id');

        $completedSales = Sale::query()->where('status', SaleStatus::COMPLETED)->count();
        $section['completed_sales'] = $completedSales;
        $section['sales_with_inventory_ledger'] = $salesWithInventory;

        $voidReversals = DB::table('canteen_inventory_transactions')
            ->where('reference_type', 'sale_void')
            ->count();

        $section['void_inventory_reversals'] = $voidReversals;

        $this->report['sections']['sales_inventory'] = $section;
    }

    protected function auditApiRoutes(): void
    {
        $section = ['title' => '7. API & Routes'];

        $required = [
            'canteen.dashboard',
            'canteen.pos',
            'canteen.api.students.search',
            'canteen.api.students.eligibility',
            'canteen.api.sales.store',
            'canteen.api.cart.validate',
            'guardian.canteen.api.summary',
            'guardian.canteen.api.children.limits.show',
            'guardian.canteen.api.children.purchases.index',
        ];

        $routes = [];
        foreach ($required as $name) {
            $routes[$name] = Route::has($name);
            if (! Route::has($name) && CanteenModule::enabled()) {
                $this->addIssue("Missing route: {$name}");
            } elseif (! Route::has($name)) {
                $this->addWarning("Route not loaded (module disabled): {$name}");
            }
        }

        $section['required_routes'] = $routes;
        $section['canteen_route_count'] = collect(Route::getRoutes())->filter(
            fn ($r) => str_starts_with($r->getName() ?? '', 'canteen.')
        )->count();

        $this->report['sections']['api_routes'] = $section;
    }

    protected function auditNotificationsFinance(): void
    {
        $section = ['title' => '8. Notifications & Finance'];

        $parentAdapter = config('canteen.integration.parent_adapter');
        $financeAdapter = config('canteen.integration.finance_adapter');

        $section['parent_adapter'] = $parentAdapter;
        $section['finance_adapter'] = $financeAdapter;

        $queuePending = Schema::hasTable('canteen_parent_visibility_queue')
            ? DB::table('canteen_parent_visibility_queue')
                ->where('visibility_status', 'pending')
                ->count()
            : 0;

        $financeEntries = Schema::hasTable('canteen_finance_entries')
            ? DB::table('canteen_finance_entries')->count()
            : 0;

        $completed = Sale::query()->where('status', SaleStatus::COMPLETED)->count();
        $completedNoFinance = 0;

        if ($financeAdapter === 'eduvera' && Schema::hasTable('canteen_finance_entries')) {
            $completedNoFinance = Sale::query()
                ->where('status', SaleStatus::COMPLETED)
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('canteen_finance_entries')
                        ->whereColumn('canteen_finance_entries.sale_id', 'canteen_sales.id');
                })
                ->count();
        }

        $section['pending_parent_visibility_queue'] = $queuePending;
        $section['finance_ledger_entries'] = $financeEntries;
        $section['completed_sales_without_finance_entry'] = $completedNoFinance;

        if ($parentAdapter === 'queued' && $queuePending > 0) {
            $this->addWarning("{$queuePending} pending parent visibility row(s) — run canteen:publish-pending-notifications");
        }

        if ($financeAdapter === 'noop') {
            $this->addWarning('Finance adapter is noop — purchases do not write canteen_finance_entries');
        }

        if ($financeAdapter === 'eduvera' && $completedNoFinance > 0) {
            $this->addIssue("{$completedNoFinance} completed sale(s) missing finance ledger entries");
        }

        $this->report['sections']['notifications_finance'] = $section;
    }

    protected function buildRecommendations(): void
    {
        $recs = [];

        if (! $this->detectAutoSyncOnUserCreate()) {
            $recs[] = 'Hook student creation (StudentController, AdmissionConversionService) to call CanteenStudentProfileSyncService::syncFromUser() when CANTEEN_ENABLED=true, or schedule students:repair-for-canteen nightly.';
        }

        if (config('canteen.integration.student_adapter') !== 'core') {
            $recs[] = 'Set CANTEEN_STUDENT_ADAPTER=core so POS auto-discovers new eligible students from users table.';
        }

        if (config('canteen.integration.wallet_adapter') !== 'user_wallet') {
            $recs[] = 'Set CANTEEN_WALLET_ADAPTER=user_wallet for real wallet debits on checkout.';
        }

        if (config('canteen.integration.parent_adapter') !== 'eduvera') {
            $recs[] = 'Set CANTEEN_PARENT_ADAPTER=eduvera for automatic guardian notifications on completed sales.';
        }

        if (config('canteen.integration.finance_adapter') !== 'eduvera') {
            $recs[] = 'Set CANTEEN_FINANCE_ADAPTER=eduvera to record purchases in canteen_finance_entries.';
        }

        $recs[] = 'Assign POS managers/cashiers via Canteen Settings → Staff (canteen_staff table); teachers need explicit registration.';
        $recs[] = 'Run php artisan students:repair-for-canteen after bulk imports; php artisan canteen:sync-guardian-links --missing-only after guardian links change.';
        $recs[] = 'Wire CanteenHealthRestrictionService::evaluateCart into PosCheckoutService to enforce allergy tag blocks at checkout (currently only whole-purchase health blocks are enforced).';

        $this->report['recommendations'] = $recs;
    }

    protected function detectAutoSyncOnUserCreate(): bool
    {
        $userFile = (new \ReflectionClass(User::class))->getFileName();
        $content = is_readable($userFile) ? (string) file_get_contents($userFile) : '';

        return str_contains($content, 'CanteenStudentProfileSyncService')
            || str_contains($content, 'observe(');
    }

    protected function activeEnrolledStudentsWithProfileCount(): int
    {
        return (int) DB::table('users as u')
            ->join('student_enrollments as e', function ($join) {
                $join->on('e.student_id', '=', 'u.id')->where('e.is_current', true);
            })
            ->join('canteen_student_profiles as p', 'p.user_id', '=', 'u.id')
            ->where('u.user_type', 'student')
            ->where('u.student_status', StudentStatus::ACTIVE)
            ->distinct('u.id')
            ->count('u.id');
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function studentsMissingProfile(int $limit): array
    {
        return DB::table('users as u')
            ->join('student_enrollments as e', function ($join) {
                $join->on('e.student_id', '=', 'u.id')->where('e.is_current', true);
            })
            ->leftJoin('canteen_student_profiles as p', 'p.user_id', '=', 'u.id')
            ->where('u.user_type', 'student')
            ->where('u.student_status', StudentStatus::ACTIVE)
            ->whereNull('p.id')
            ->select('u.id', 'u.name', 'u.student_code')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'student_code' => $r->student_code])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function profileEnrollmentMismatches(int $limit): array
    {
        $rows = [];

        StudentProfile::query()
            ->whereNotNull('user_id')
            ->with(['user.currentStudentEnrollment'])
            ->limit(500)
            ->get()
            ->each(function (StudentProfile $profile) use (&$rows, $limit) {
                $enrollment = $profile->user?->currentStudentEnrollment;
                if (! $enrollment) {
                    return;
                }

                $gradeMismatch = $profile->grade && $enrollment->grade_name
                    && $profile->grade !== $enrollment->grade_name;
                $classMismatch = $profile->class_name && $enrollment->class_name
                    && $profile->class_name !== $enrollment->class_name;

                if (($gradeMismatch || $classMismatch) && count($rows) < $limit) {
                    $rows[] = [
                        'user_id' => $profile->user_id,
                        'profile_grade' => $profile->grade,
                        'enrollment_grade' => $enrollment->grade_name,
                        'profile_class' => $profile->class_name,
                        'enrollment_class' => $enrollment->class_name,
                    ];
                }
            });

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function sampleStudentsWithoutEnrollment(int $limit): array
    {
        return User::query()
            ->where('user_type', 'student')
            ->where('student_status', StudentStatus::ACTIVE)
            ->whereDoesntHave('currentStudentEnrollment')
            ->limit($limit)
            ->get(['id', 'name', 'student_code', 'student_status'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'code' => $u->student_code])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function studentsWithoutGuardianProfile(int $limit): array
    {
        return StudentProfile::query()
            ->whereNotNull('user_id')
            ->whereNull('primary_guardian_user_id')
            ->with('user:id,name,student_code')
            ->limit($limit)
            ->get()
            ->map(fn (StudentProfile $p) => [
                'user_id' => $p->user_id,
                'name' => $p->user?->name,
                'student_code' => $p->user?->student_code,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function studentsWithPivotButNoProfileGuardian(int $limit): array
    {
        return DB::table('guardian_student as gs')
            ->join('canteen_student_profiles as p', 'p.user_id', '=', 'gs.student_id')
            ->whereNull('p.primary_guardian_user_id')
            ->select('gs.student_id', 'p.id as profile_id')
            ->distinct()
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['user_id' => $r->student_id, 'profile_id' => $r->profile_id])
            ->all();
    }

    protected function addIssue(string $message): void
    {
        $this->report['issues'][] = $message;
    }

    protected function addWarning(string $message): void
    {
        $this->report['warnings'][] = $message;
    }

    protected function renderReport(): void
    {
        $this->newLine();
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║     EDUVERA CANTEEN — FULL SYSTEM INTEGRATION AUDIT         ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->line('Generated: '.$this->report['generated_at']);
        $this->line('Module: '.($this->report['module_enabled'] ? 'ENABLED' : 'DISABLED'));
        $this->line('Adapters: '.json_encode($this->report['adapters'], JSON_UNESCAPED_UNICODE));
        $this->newLine();

        foreach ($this->report['sections'] as $key => $section) {
            $this->comment($section['title']);
            unset($section['title']);
            foreach ($section as $k => $v) {
                if (is_array($v) && $v !== [] && array_is_list($v) && isset($v[0]) && is_array($v[0])) {
                    $this->line("  {$k}:");
                    foreach (array_slice($v, 0, 5) as $row) {
                        $this->line('    - '.json_encode($row, JSON_UNESCAPED_UNICODE));
                    }
                    if (count($v) > 5) {
                        $this->line('    ... +'.(count($v) - 5).' more');
                    }
                } elseif (is_array($v)) {
                    $this->line("  {$k}: ".json_encode($v, JSON_UNESCAPED_UNICODE));
                } else {
                    $this->line("  {$k}: {$v}");
                }
            }
            $this->newLine();
        }

        if ($this->report['warnings'] !== []) {
            $this->line('<fg=yellow>WARNINGS ('.count($this->report['warnings']).')</>');
            foreach ($this->report['warnings'] as $w) {
                $this->line("  ! {$w}");
            }
            $this->newLine();
        }

        if ($this->report['issues'] !== []) {
            $this->line('<fg=red>ISSUES ('.count($this->report['issues']).')</>');
            foreach ($this->report['issues'] as $i) {
                $this->line("  ✗ {$i}");
            }
            $this->newLine();
        }

        $this->line('<fg=cyan>RECOMMENDATIONS</>');
        foreach ($this->report['recommendations'] as $r) {
            $this->line("  → {$r}");
        }
    }
}
