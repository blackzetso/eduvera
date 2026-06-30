<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Admin\PermissionService;

class FinancePolicy
{
    public function __construct(
        protected PermissionService $permissions,
    ) {}

    public function walletAdjust(User $user): bool
    {
        return $this->permissions->can($user, 'finance.wallet_adjust');
    }

    public function installmentOverride(User $user): bool
    {
        return $this->permissions->can($user, 'finance.installment_override');
    }

    public function financialCorrection(User $user): bool
    {
        return $this->permissions->can($user, 'finance.financial_correction');
    }
}
