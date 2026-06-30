<?php

namespace App\Support\Admin;

use App\Models\User;

class PermissionService
{
    public function role(User $user): string
    {
        if (! $user->isAdmin()) {
            return '';
        }

        return AdminRole::normalize($user->role);
    }

    public function can(User $user, string $permission): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        $role = $this->role($user);
        $matrix = config('admin_permissions.matrix.'.$role, []);

        if ($matrix === '*') {
            return true;
        }

        return in_array($permission, $matrix, true);
    }

    /**
     * @return array<string, bool>
     */
    public function abilitiesFor(User $user): array
    {
        $permissions = config('admin_permissions.permissions', []);
        $abilities = [];

        foreach ($permissions as $permission) {
            $abilities[$permission] = $this->can($user, $permission);
        }

        return $abilities;
    }
}
