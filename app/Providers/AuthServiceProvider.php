<?php

namespace App\Providers;

use App\Models\Admission\AdmissionApplication;
use App\Models\User;
use App\Policies\AdmissionApplicationPolicy;
use App\Policies\FamilyPolicy;
use App\Policies\FinancePolicy;
use App\Policies\StudentLifecyclePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        AdmissionApplication::class => AdmissionApplicationPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        $lifecycle = StudentLifecyclePolicy::class;
        $family = FamilyPolicy::class;
        $finance = FinancePolicy::class;

        Gate::define('lifecycle.promote', fn (User $user, User $student) => app($lifecycle)->promote($user, $student));
        Gate::define('lifecycle.transfer', fn (User $user, User $student) => app($lifecycle)->transfer($user, $student));
        Gate::define('lifecycle.withdraw', fn (User $user, User $student) => app($lifecycle)->withdraw($user, $student));
        Gate::define('lifecycle.reEnroll', fn (User $user, User $student) => app($lifecycle)->reEnroll($user, $student));
        Gate::define('lifecycle.graduate', fn (User $user, User $student) => app($lifecycle)->graduate($user, $student));
        Gate::define('lifecycle.changeStatus', fn (User $user, User $student) => app($lifecycle)->changeStatus($user, $student));
        Gate::define('lifecycle.linkGuardian', fn (User $user, User $student) => app($lifecycle)->linkGuardian($user, $student));

        Gate::define('family.editProfile', fn (User $user, User $guardian) => app($family)->editProfile($user, $guardian));
        Gate::define('family.linkStudent', fn (User $user, User $guardian) => app($family)->linkStudent($user, $guardian));
        Gate::define('family.removeLink', fn (User $user, User $guardian) => app($family)->removeLink($user, $guardian));

        Gate::define('finance.walletAdjust', fn (User $user) => app($finance)->walletAdjust($user));
        Gate::define('finance.installmentOverride', fn (User $user) => app($finance)->installmentOverride($user));
        Gate::define('finance.financialCorrection', fn (User $user) => app($finance)->financialCorrection($user));
    }
}
