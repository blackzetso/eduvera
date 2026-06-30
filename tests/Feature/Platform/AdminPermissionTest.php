<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AdminPermissionTest extends TestCase
{
    protected function adminUser(string $role = 'super_admin'): User
    {
        return new User([
            'user_type' => 'admin',
            'role' => $role,
        ]);
    }

    public function test_admissions_officer_cannot_convert_application(): void
    {
        $admin = $this->adminUser('admissions_officer');

        $this->assertFalse(Gate::forUser($admin)->allows('convert', new \App\Models\Admission\AdmissionApplication));
    }

    public function test_registrar_can_convert_application(): void
    {
        $admin = $this->adminUser('registrar');

        $this->assertTrue(Gate::forUser($admin)->allows('convert', new \App\Models\Admission\AdmissionApplication));
    }

    public function test_registrar_cannot_accept_application(): void
    {
        $admin = $this->adminUser('registrar');

        $this->assertFalse(Gate::forUser($admin)->allows('accept', new \App\Models\Admission\AdmissionApplication));
    }

    public function test_admissions_officer_can_accept_application(): void
    {
        $admin = $this->adminUser('admissions_officer');

        $this->assertTrue(Gate::forUser($admin)->allows('accept', new \App\Models\Admission\AdmissionApplication));
    }

    public function test_finance_officer_cannot_accept_or_convert(): void
    {
        $admin = $this->adminUser('finance_officer');
        $application = new \App\Models\Admission\AdmissionApplication;

        $this->assertFalse(Gate::forUser($admin)->allows('accept', $application));
        $this->assertFalse(Gate::forUser($admin)->allows('convert', $application));
    }

    public function test_hr_officer_cannot_make_admissions_decisions(): void
    {
        $admin = $this->adminUser('hr_officer');
        $application = new \App\Models\Admission\AdmissionApplication;

        $this->assertFalse(Gate::forUser($admin)->allows('accept', $application));
        $this->assertFalse(Gate::forUser($admin)->allows('reject', $application));
    }

    public function test_finance_officer_cannot_promote_student(): void
    {
        $admin = $this->adminUser('finance_officer');
        $student = new User(['user_type' => 'student']);

        $this->assertFalse(Gate::forUser($admin)->allows('lifecycle.promote', $student));
    }

    public function test_super_admin_has_all_permissions_by_default(): void
    {
        $admin = $this->adminUser('super_admin');

        $this->assertTrue($admin->hasAdminPermission('admissions.convert'));
        $this->assertTrue($admin->hasAdminPermission('lifecycle.promote'));
        $this->assertTrue($admin->hasAdminPermission('finance.wallet_adjust'));
    }

    public function test_null_role_defaults_to_super_admin(): void
    {
        $admin = new User(['user_type' => 'admin', 'role' => null]);

        $this->assertSame('super_admin', $admin->adminRole());
        $this->assertTrue($admin->hasAdminPermission('admissions.accept'));
    }
}
