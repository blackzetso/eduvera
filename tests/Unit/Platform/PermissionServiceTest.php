<?php

namespace Tests\Unit\Platform;

use App\Models\User;
use App\Support\Admin\PermissionService;
use Tests\TestCase;

class PermissionServiceTest extends TestCase
{
    public function test_role_matrix_grants_expected_permissions(): void
    {
        $service = new PermissionService;

        $officer = new User(['user_type' => 'admin', 'role' => 'admissions_officer']);
        $registrar = new User(['user_type' => 'admin', 'role' => 'registrar']);

        $this->assertTrue($service->can($officer, 'admissions.accept'));
        $this->assertFalse($service->can($officer, 'admissions.convert'));
        $this->assertTrue($service->can($registrar, 'admissions.convert'));
        $this->assertTrue($service->can($registrar, 'lifecycle.promote'));
    }
}
