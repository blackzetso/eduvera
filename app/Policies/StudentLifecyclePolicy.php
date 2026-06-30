<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Admin\PermissionService;

class StudentLifecyclePolicy
{
    public function __construct(
        protected PermissionService $permissions,
    ) {}

    public function promote(User $user, User $student): bool
    {
        return $this->permissions->can($user, 'lifecycle.promote');
    }

    public function transfer(User $user, User $student): bool
    {
        return $this->permissions->can($user, 'lifecycle.transfer');
    }

    public function withdraw(User $user, User $student): bool
    {
        return $this->permissions->can($user, 'lifecycle.withdraw');
    }

    public function reEnroll(User $user, User $student): bool
    {
        return $this->permissions->can($user, 'lifecycle.re_enroll');
    }

    public function graduate(User $user, User $student): bool
    {
        return $this->permissions->can($user, 'lifecycle.graduate');
    }

    public function changeStatus(User $user, User $student): bool
    {
        return $this->permissions->can($user, 'lifecycle.change_status');
    }

    public function linkGuardian(User $user, User $student): bool
    {
        return $this->permissions->can($user, 'lifecycle.link_guardian');
    }
}
