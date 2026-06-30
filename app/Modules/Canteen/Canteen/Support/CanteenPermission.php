<?php

namespace App\Modules\Canteen\Support;

use App\Models\User;
use App\Modules\Canteen\CanteenModule;
use App\Modules\Canteen\Models\Staff;

class CanteenPermission
{
    public static function allows(?User $user, string $permission): bool
    {
        if (! $user || ! CanteenModule::enabled()) {
            return false;
        }

        if ($user->user_type === 'admin') {
            return true;
        }

        if (! $user->getKey() || ! self::isValidPermission($permission)) {
            return false;
        }

        $staff = Staff::query()
            ->where('user_id', $user->getKey())
            ->where('is_active', true)
            ->first();

        if (! $staff) {
            return false;
        }

        return in_array($permission, config("canteen.roles.{$staff->role}", []), true);
    }

    public static function forUser(?User $user): array
    {
        if (! $user || ! CanteenModule::enabled()) {
            return [];
        }

        if ($user->user_type === 'admin') {
            return config('canteen.permissions', []);
        }

        if (! $user->getKey()) {
            return [];
        }

        $staff = Staff::query()
            ->where('user_id', $user->getKey())
            ->where('is_active', true)
            ->first();

        return $staff ? config("canteen.roles.{$staff->role}", []) : [];
    }

    public static function isValidPermission(string $permission): bool
    {
        return in_array($permission, config('canteen.permissions', []), true);
    }
}
