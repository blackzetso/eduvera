<?php

namespace App\Policies;

use App\Models\Admission\AdmissionApplication;
use App\Models\User;
use App\Support\Admin\PermissionService;

class AdmissionApplicationPolicy
{
    public function __construct(
        protected PermissionService $permissions,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->permissions->can($user, 'admissions.view');
    }

    public function view(User $user, AdmissionApplication $application): bool
    {
        return $this->permissions->can($user, 'admissions.view');
    }

    public function manage(User $user, AdmissionApplication $application): bool
    {
        return $this->permissions->can($user, 'admissions.manage');
    }

    public function accept(User $user, AdmissionApplication $application): bool
    {
        return $this->permissions->can($user, 'admissions.accept');
    }

    public function reject(User $user, AdmissionApplication $application): bool
    {
        return $this->permissions->can($user, 'admissions.reject');
    }

    public function waitlist(User $user, AdmissionApplication $application): bool
    {
        return $this->permissions->can($user, 'admissions.waitlist');
    }

    public function withdraw(User $user, AdmissionApplication $application): bool
    {
        return $this->permissions->can($user, 'admissions.withdraw');
    }

    public function convert(User $user, AdmissionApplication $application): bool
    {
        return $this->permissions->can($user, 'admissions.convert');
    }
}
