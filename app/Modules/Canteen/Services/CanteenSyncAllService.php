<?php

namespace App\Modules\Canteen\Services;

use App\Models\User;
use App\Modules\Canteen\CanteenModule;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Models\Staff;
use App\Modules\Canteen\Models\StudentProfile;
use App\Modules\Canteen\Support\CanteenSettingKeys;
use App\Modules\Canteen\Support\SaleStatus;
use App\Services\StudentEnrollmentService;
use App\Support\Student\StudentStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CanteenSyncAllService
{
    public const CHUNK_SIZE = 100;

    public function __construct(
        protected CanteenStudentProfileSyncService $studentSync,
        protected CanteenGuardianProfileSyncService $guardianSync,
        protected CanteenPurchaseGuardianSyncService $purchaseGuardianSync,
        protected CanteenHealthRestrictionBootstrapService $healthBootstrap,
        protected CanteenStaffRegistrationService $staffRegistration,
        protected CanteenSettingsService $settings,
        protected StudentEnrollmentService $enrollments,
    ) {}

    /**
     * @param  array{
     *     dry_run?: bool,
     *     skip_staff?: bool,
     *     skip_guardians?: bool,
     *     default_role?: string,
     *     role_specs?: list<string>,
     *     guardian_missing_only?: bool,
     *     manager_user_id?: int|string|null,
     *     manager_email?: string|null,
     *     persist_manager?: bool,
     * }  $options
     * @return array<string, mixed>
     */
    public function run(array $options = []): array
    {
        if (! CanteenModule::enabled()) {
            throw new InvalidArgumentException('Canteen module is disabled (CANTEEN_ENABLED=false).');
        }

        $dryRun = (bool) ($options['dry_run'] ?? false);
        $manager = $this->resolveManager($options);

        $stats = [
            'profiles_created' => 0,
            'profiles_updated' => 0,
            'staff_registered' => 0,
            'staff_updated' => 0,
            'staff_unchanged' => 0,
            'staff_teachers' => [],
            'staff_skipped' => [],
            'manager_user_id' => $manager['user_id'],
            'manager_source' => $manager['source'],
            'guardians_synced' => 0,
            'purchase_guardians_synced' => 0,
            'missing_guardians' => [],
            'missing_profiles' => [],
            'unregistered_teachers' => [],
            'health_records_updated' => 0,
            'health_summaries' => [],
            'gaps' => [],
            'has_gaps' => false,
        ];

        $studentStats = $this->syncStudentProfiles($dryRun);
        $stats['profiles_created'] = $studentStats['created'];
        $stats['profiles_updated'] = $studentStats['updated'];

        if (! ($options['skip_staff'] ?? false)) {
            $roleSpecs = $this->mergeRoleSpecs($options['role_specs'] ?? []);

            if ($manager['role_spec'] !== null) {
                $roleSpecs[] = $manager['role_spec'];
            }

            $staffStats = $this->staffRegistration->registerAllTeachers([
                'default_role' => $options['default_role'] ?? config('canteen.teacher_staff.default_role', 'cashier'),
                'role_specs' => $roleSpecs,
                'dry_run' => $dryRun,
            ]);

            $stats['staff_registered'] = $staffStats['registered'];
            $stats['staff_updated'] = $staffStats['updated'];
            $stats['staff_unchanged'] = $staffStats['unchanged'];
            $stats['staff_teachers'] = $staffStats['teachers'];
            $stats['staff_skipped'] = $staffStats['skipped'];

            if ($manager['persist'] && ! $dryRun) {
                $this->settings->persistManagerUserId($manager['user_id']);
            }
        }

        if (! ($options['skip_guardians'] ?? false)) {
            $stats['guardians_synced'] = $this->syncGuardianLinks(
                (bool) ($options['guardian_missing_only'] ?? true),
                $dryRun,
            );
            $stats['purchase_guardians_synced'] = $this->syncPurchaseGuardians($dryRun);
        }

        $stats['health_records_updated'] = $this->syncHealthRestrictions($dryRun);
        $stats['missing_guardians'] = $this->collectMissingGuardians();
        $stats['missing_profiles'] = $this->collectMissingProfiles();
        $stats['health_summaries'] = $this->collectHealthSummaries();
        $stats['unregistered_teachers'] = ($options['skip_staff'] ?? false)
            ? []
            : $this->collectUnregisteredTeachers();
        $stats['gaps'] = $this->detectGaps(
            $stats,
            (bool) ($options['skip_staff'] ?? false),
        );
        $stats['has_gaps'] = $stats['gaps'] !== [];

        return $stats;
    }

    /**
     * @param  array{
     *     manager_user_id?: int|string|null,
     *     manager_email?: string|null,
     *     persist_manager?: bool,
     * }  $options
     * @return array{
     *     user_id: ?int,
     *     source: string,
     *     role_spec: ?string,
     *     persist: bool,
     * }
     */
    public function resolveManager(array $options): array
    {
        $persist = (bool) ($options['persist_manager'] ?? false);
        $userId = null;
        $source = 'none';

        if (! empty($options['manager_user_id'])) {
            $userId = (int) $options['manager_user_id'];
            $source = 'cli_user_id';
            $persist = true;
        } elseif (! empty($options['manager_email'])) {
            $user = User::query()->where('email', trim((string) $options['manager_email']))->first();

            if (! $user) {
                throw new InvalidArgumentException('Manager email not found in users table: '.$options['manager_email']);
            }

            $userId = $user->id;
            $source = 'cli_email';
            $persist = true;
        } else {
            $storedId = $this->settings->managerUserId();

            if ($storedId) {
                $userId = $storedId;
                $source = $this->settings->get(CanteenSettingKeys::MANAGER_USER_ID) !== null
                    ? 'canteen_settings'
                    : 'config';
            }
        }

        if ($userId === null) {
            return [
                'user_id' => null,
                'source' => 'none',
                'role_spec' => null,
                'persist' => false,
            ];
        }

        $teacher = User::query()
            ->where('id', $userId)
            ->where('user_type', 'teacher')
            ->first(['id', 'name', 'email']);

        if (! $teacher) {
            $existing = User::query()->find($userId, ['id', 'name', 'user_type']);

            if (! $existing) {
                throw new InvalidArgumentException("Manager user #{$userId} was not found in users table.");
            }

            throw new InvalidArgumentException(
                "Manager user #{$userId} ({$existing->name}) is not a teacher (user_type={$existing->user_type})."
            );
        }

        return [
            'user_id' => $teacher->id,
            'source' => $source,
            'role_spec' => "{$teacher->id}:manager",
            'persist' => $persist,
        ];
    }

    /**
     * @return array{created: int, updated: int}
     */
    protected function syncStudentProfiles(bool $dryRun): array
    {
        $created = 0;
        $updated = 0;
        $defaultLimit = $this->settings->defaultDailyLimit();

        $this->activeEnrolledStudentsQuery()
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function ($students) use ($dryRun, $defaultLimit, &$created, &$updated) {
                DB::transaction(function () use ($students, $dryRun, $defaultLimit, &$created, &$updated) {
                    foreach ($students as $student) {
                        $profile = $this->findProfileForStudent($student);
                        $needsSync = $this->profileNeedsSync($student, $profile);

                        if (! $needsSync) {
                            continue;
                        }

                        if (! $profile) {
                            $created++;
                        } else {
                            $updated++;
                        }

                        if ($dryRun) {
                            continue;
                        }

                        $profile = $this->studentSync->syncFromUser($student);

                        if ($profile->daily_spending_limit === null && $defaultLimit !== null) {
                            $profile->update(['daily_spending_limit' => $defaultLimit]);
                        }
                    }
                });
            });

        return ['created' => $created, 'updated' => $updated];
    }

    protected function activeEnrolledStudentsQuery()
    {
        return User::query()
            ->where('user_type', 'student')
            ->where('student_status', StudentStatus::ACTIVE)
            ->whereHas('currentStudentEnrollment');
    }

    protected function findProfileForStudent(User $student): ?StudentProfile
    {
        return StudentProfile::query()
            ->where(fn ($q) => $q
                ->where('user_id', $student->id)
                ->orWhere('student_id_ref', (string) $student->id))
            ->first();
    }

    public function profileNeedsSync(User $student, ?StudentProfile $profile): bool
    {
        if (! $profile) {
            return true;
        }

        $enrollment = $this->enrollments->currentEnrollment($student);

        return $profile->user_id !== $student->id
            || $profile->student_name !== $student->name
            || $profile->grade !== $enrollment?->grade_name
            || $profile->class_name !== $enrollment?->class_name
            || ! $profile->is_active;
    }

    protected function syncGuardianLinks(bool $missingOnly, bool $dryRun): int
    {
        $synced = 0;
        $activeStudentIds = $this->activeEnrolledStudentsQuery()->pluck('id');

        $query = StudentProfile::query()
            ->whereNotNull('user_id')
            ->whereIn('user_id', $activeStudentIds)
            ->when($missingOnly, fn ($q) => $q->whereNull('primary_guardian_user_id'));

        $query->orderBy('student_name')->chunkById(self::CHUNK_SIZE, function ($profiles) use ($dryRun, &$synced) {
            DB::transaction(function () use ($profiles, $dryRun, &$synced) {
                foreach ($profiles as $profile) {
                    $student = User::query()->students()->find($profile->user_id);

                    if (! $student) {
                        continue;
                    }

                    if ($dryRun) {
                        $synced++;

                        continue;
                    }

                    $this->guardianSync->syncForStudent($student, $profile);
                    $synced++;
                }
            });
        });

        return $synced;
    }

    protected function syncPurchaseGuardians(bool $dryRun): int
    {
        if ($dryRun) {
            return Sale::query()
                ->whereNull('primary_guardian_user_id')
                ->whereIn('status', [SaleStatus::COMPLETED, SaleStatus::PENDING_PAYMENT, SaleStatus::VOIDED])
                ->count();
        }

        return $this->purchaseGuardianSync->syncAllMissing();
    }

    protected function syncHealthRestrictions(bool $dryRun): int
    {
        $updated = 0;
        $activeStudentIds = $this->activeEnrolledStudentsQuery()->pluck('id');

        StudentProfile::query()
            ->whereNotNull('user_id')
            ->whereIn('user_id', $activeStudentIds)
            ->orderBy('student_name')
            ->chunkById(self::CHUNK_SIZE, function ($profiles) use ($dryRun, &$updated) {
                DB::transaction(function () use ($profiles, $dryRun, &$updated) {
                    foreach ($profiles as $profile) {
                        if ($dryRun) {
                            if ($this->healthBootstrap->needsBootstrap($profile)
                                || $this->healthBootstrap->needsTagPropagation($profile)) {
                                $updated++;
                            }

                            continue;
                        }

                        $changed = $this->healthBootstrap->ensureForProfile($profile)
                            || $this->healthBootstrap->propagateBlockedProductTags($profile->fresh());

                        if ($changed) {
                            $updated++;
                        }
                    }
                });
            });

        return $updated;
    }

    /**
     * @return list<array{user_id: int, student_id_ref: string, name: string}>
     */
    public function collectMissingGuardians(): array
    {
        $activeStudentIds = $this->activeEnrolledStudentsQuery()->pluck('id');

        return StudentProfile::query()
            ->whereNotNull('user_id')
            ->whereIn('user_id', $activeStudentIds)
            ->whereNull('primary_guardian_user_id')
            ->orderBy('student_name')
            ->get(['user_id', 'student_id_ref', 'student_name'])
            ->map(fn (StudentProfile $profile) => [
                'user_id' => (int) $profile->user_id,
                'student_id_ref' => (string) $profile->student_id_ref,
                'name' => (string) $profile->student_name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{user_id: int, name: string, student_code: string}>
     */
    public function collectMissingProfiles(): array
    {
        return $this->activeEnrolledStudentsQuery()
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('canteen_student_profiles as p')
                    ->whereNull('p.deleted_at')
                    ->where(function ($inner) {
                        $inner->whereColumn('p.user_id', 'users.id')
                            ->orWhereColumn('p.student_id_ref', DB::raw('CAST(users.id AS CHAR)'));
                    });
            })
            ->orderBy('name')
            ->get(['id', 'name', 'student_code'])
            ->map(fn (User $student) => [
                'user_id' => (int) $student->id,
                'name' => (string) $student->name,
                'student_code' => (string) ($student->student_code ?? '—'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, email: string}>
     */
    public function collectUnregisteredTeachers(): array
    {
        $registeredIds = Staff::query()
            ->where('is_active', true)
            ->pluck('user_id')
            ->all();

        return User::query()
            ->where('user_type', 'teacher')
            ->when($registeredIds !== [], fn ($q) => $q->whereNotIn('id', $registeredIds))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $teacher) => [
                'id' => (int) $teacher->id,
                'name' => (string) $teacher->name,
                'email' => (string) $teacher->email,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     user_id: int,
     *     student_id_ref: string,
     *     name: string,
     *     allergies_count: int,
     *     blocked_tags_count: int,
     *     block_all_purchases: bool,
     * }>
     */
    public function collectHealthSummaries(): array
    {
        $activeStudentIds = $this->activeEnrolledStudentsQuery()->pluck('id');

        return StudentProfile::query()
            ->whereNotNull('user_id')
            ->whereIn('user_id', $activeStudentIds)
            ->orderBy('student_name')
            ->get(['user_id', 'student_id_ref', 'student_name', 'health_restrictions'])
            ->map(function (StudentProfile $profile) {
                $restrictions = is_array($profile->health_restrictions)
                    ? $profile->health_restrictions
                    : [];

                return [
                    'user_id' => (int) $profile->user_id,
                    'student_id_ref' => (string) $profile->student_id_ref,
                    'name' => (string) $profile->student_name,
                    'allergies_count' => count($restrictions['allergies'] ?? []),
                    'blocked_tags_count' => count($restrictions['blocked_tags'] ?? []),
                    'block_all_purchases' => (bool) ($restrictions['block_all_purchases'] ?? false),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return list<string>
     */
    public function detectGaps(array $stats, bool $skipStaff): array
    {
        $gaps = [];

        foreach ($stats['missing_profiles'] ?? [] as $row) {
            $gaps[] = "Active student #{$row['user_id']} ({$row['name']}) has no canteen profile";
        }

        foreach ($stats['missing_guardians'] ?? [] as $row) {
            $gaps[] = "Student #{$row['user_id']} ({$row['name']}) has no primary guardian";
        }

        if (! $skipStaff) {
            foreach ($stats['unregistered_teachers'] ?? [] as $row) {
                $gaps[] = "Teacher #{$row['id']} ({$row['name']}) is not registered in canteen_staff";
            }

            foreach ($stats['staff_skipped'] ?? [] as $row) {
                $gaps[] = "Staff registration skipped for teacher #{$row['id']} ({$row['name']}): {$row['reason']}";
            }
        }

        return $gaps;
    }

    /**
     * @param  list<string>  $cliSpecs
     * @return list<string>
     */
    public function mergeRoleSpecs(array $cliSpecs): array
    {
        $specs = [];

        foreach (config('canteen.full_sync.staff', []) as $row) {
            if (is_string($row)) {
                $specs[] = $row;
            }
        }

        $envSpecs = array_filter([
            (string) config('canteen.full_sync.staff_env', ''),
            (string) config('canteen.teacher_staff.roles_env', ''),
        ]);

        return array_values(array_filter(array_merge($envSpecs, $specs, $cliSpecs)));
    }
}
