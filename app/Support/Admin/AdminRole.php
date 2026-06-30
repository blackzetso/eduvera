<?php

namespace App\Support\Admin;

class AdminRole
{
    public const SUPER_ADMIN = 'super_admin';

    public const PRINCIPAL = 'principal';

    public const ADMISSIONS_OFFICER = 'admissions_officer';

    public const REGISTRAR = 'registrar';

    public const FINANCE_OFFICER = 'finance_officer';

    public const HR_OFFICER = 'hr_officer';

    public static function all(): array
    {
        return array_keys(config('admin_permissions.roles', []));
    }

    public static function label(?string $role): string
    {
        if (! $role) {
            return config('admin_permissions.roles.'.self::SUPER_ADMIN, 'Super Admin');
        }

        return config('admin_permissions.roles.'.$role, $role);
    }

    public static function normalize(?string $role): string
    {
        if (! $role || ! in_array($role, self::all(), true)) {
            return config('admin_permissions.default_role', self::SUPER_ADMIN);
        }

        return $role;
    }
}
