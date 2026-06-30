<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Admin\PermissionService;

class FamilyPolicy
{
    public function __construct(
        protected PermissionService $permissions,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $guardian): bool
    {
        return $user->isAdmin();
    }

    public function editProfile(User $user, User $guardian): bool
    {
        return $this->permissions->can($user, 'family.edit_profile');
    }

    public function linkStudent(User $user, User $guardian): bool
    {
        return $this->permissions->can($user, 'family.link_student');
    }

    public function removeLink(User $user, User $guardian): bool
    {
        return $this->permissions->can($user, 'family.remove_link');
    }
}
