<?php

namespace App\Modules\Canteen\Services;

use App\Models\User;
use App\Modules\Canteen\Models\Staff;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CanteenStaffRegistrationService
{
    /**
     * @param  array{
     *     default_role?: string,
     *     role_specs?: list<string>,
     *     dry_run?: bool,
     * }  $options
     * @return array{
     *     teachers: list<array{id: int, name: string, email: string, role: string}>,
     *     registered: int,
     *     updated: int,
     *     unchanged: int,
     *     skipped: list<array{id: int, name: string, reason: string}>,
     * }
     */
    public function registerAllTeachers(array $options = []): array
    {
        $defaultRole = $this->normalizeRole($options['default_role'] ?? config('canteen.teacher_staff.default_role', 'cashier'));
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $roleMap = $this->buildRoleMap($options['role_specs'] ?? []);

        $stats = [
            'teachers' => [],
            'registered' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped' => [],
        ];

        User::query()
            ->where('user_type', 'teacher')
            ->orderBy('id')
            ->chunkById(100, function ($teachers) use ($defaultRole, $roleMap, $dryRun, &$stats) {
                DB::transaction(function () use ($teachers, $defaultRole, $roleMap, $dryRun, &$stats) {
                    foreach ($teachers as $teacher) {
                        $role = $this->resolveRoleForTeacher($teacher, $defaultRole, $roleMap);

                        try {
                            $outcome = $this->registerTeacher($teacher, $defaultRole, $roleMap, $dryRun);
                            $stats[$outcome]++;
                            $stats['teachers'][] = [
                                'id' => $teacher->id,
                                'name' => $teacher->name,
                                'email' => $teacher->email,
                                'role' => $role,
                                'outcome' => $outcome,
                            ];
                        } catch (\Throwable $e) {
                            $stats['skipped'][] = [
                                'id' => $teacher->id,
                                'name' => $teacher->name,
                                'reason' => $e->getMessage(),
                            ];
                        }
                    }
                });
            });

        usort($stats['teachers'], fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return $stats;
    }

    /**
     * @param  array<string, string>  $roleMap  Keys: numeric user id or lowercase email
     * @return 'registered'|'updated'|'unchanged'
     */
    public function registerTeacher(User $teacher, string $defaultRole, array $roleMap, bool $dryRun = false): string
    {
        $role = $this->resolveRoleForTeacher($teacher, $defaultRole, $roleMap);
        $existing = Staff::query()->where('user_id', $teacher->id)->first();

        if ($dryRun) {
            if (! $existing) {
                return 'registered';
            }

            if ($existing->role !== $role || ! $existing->is_active) {
                return 'updated';
            }

            return 'unchanged';
        }

        Staff::query()->updateOrCreate(
            ['user_id' => $teacher->id],
            ['role' => $role, 'is_active' => true],
        );

        if (! $existing) {
            return 'registered';
        }

        if ($existing->role !== $role || ! $existing->is_active) {
            return 'updated';
        }

        return 'unchanged';
    }

    public function resolveRoleForTeacher(User $teacher, string $defaultRole, array $roleMap): string
    {
        $byId = $roleMap[(string) $teacher->id] ?? null;
        $byEmail = $roleMap[strtolower((string) $teacher->email)] ?? null;
        $role = $byId ?? $byEmail ?? $defaultRole;

        return $this->normalizeRole($role);
    }

    /**
     * @param  list<string>  $extraSpecs
     * @return array<string, string>
     */
    public function buildRoleMap(array $extraSpecs = []): array
    {
        $map = [];

        foreach (config('canteen.full_sync.staff', []) as $row) {
            if (is_string($row)) {
                foreach ($this->parseStaffSpec($row) as $entry) {
                    $map[$this->roleMapKey($entry['identifier'])] = $entry['role'];
                }

                continue;
            }

            if (! is_array($row)) {
                continue;
            }

            $role = $row['role'] ?? null;

            if (! in_array($role, ['manager', 'cashier'], true)) {
                continue;
            }

            if (isset($row['user_id'])) {
                $map[(string) $row['user_id']] = $role;
            }

            if (! empty($row['email']) && is_string($row['email'])) {
                $map[strtolower($row['email'])] = $role;
            }
        }

        $envSpecs = array_filter([
            (string) config('canteen.full_sync.staff_env', ''),
            (string) config('canteen.teacher_staff.roles_env', ''),
        ]);

        foreach ($envSpecs as $spec) {
            foreach ($this->parseStaffSpec($spec) as $entry) {
                $map[$this->roleMapKey($entry['identifier'])] = $entry['role'];
            }
        }

        foreach ($extraSpecs as $spec) {
            foreach ($this->parseStaffSpec($spec) as $entry) {
                $map[$this->roleMapKey($entry['identifier'])] = $entry['role'];
            }
        }

        return $map;
    }

    /**
     * @return list<array{identifier: string, role: string}>
     */
    public function parseStaffSpec(string $spec): array
    {
        $entries = [];
        $spec = trim($spec);

        if ($spec === '') {
            return [];
        }

        foreach (explode(',', $spec) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (! str_contains($part, ':')) {
                throw new InvalidArgumentException("Invalid staff spec [{$part}]. Use identifier:role (e.g. 12:manager or teacher@school.edu:cashier).");
            }

            [$identifier, $role] = array_map('trim', explode(':', $part, 2));

            if ($identifier === '') {
                throw new InvalidArgumentException("Invalid staff spec [{$part}]. Identifier cannot be empty.");
            }

            $entries[] = [
                'identifier' => $identifier,
                'role' => $this->normalizeRole($role),
            ];
        }

        return $entries;
    }

    protected function roleMapKey(string $identifier): string
    {
        $identifier = trim($identifier);

        return ctype_digit($identifier) ? $identifier : strtolower($identifier);
    }

    protected function normalizeRole(string $role): string
    {
        $role = strtolower(trim($role));

        if (! in_array($role, ['manager', 'cashier'], true)) {
            throw new InvalidArgumentException("Invalid canteen staff role [{$role}]. Use manager or cashier.");
        }

        return $role;
    }
}
