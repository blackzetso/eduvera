<?php

namespace Tests\Unit\Dova;

use App\Models\User;
use App\Support\Dova\DovaContextResolver;
use Illuminate\Http\Request;
use Tests\TestCase;

class DovaContextResolverTest extends TestCase
{
    protected DovaContextResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new DovaContextResolver;
    }

    public function test_detects_home_context_on_root_path(): void
    {
        $ctx = $this->resolver->resolve(Request::create('/', 'GET'));

        $this->assertSame('public', $ctx['portal']);
        $this->assertSame('guest', $ctx['role']);
        $this->assertSame('home', $ctx['page_context']);
    }

    public function test_detects_admin_students_context_from_path(): void
    {
        $ctx = $this->resolver->resolve(Request::create('/admin/students', 'GET'));

        $this->assertSame('admin', $ctx['portal']);
        $this->assertSame('students', $ctx['page_context']);
        $this->assertStringContainsString('Students', $ctx['summary']);
    }

    public function test_uses_client_path_for_spa_navigation(): void
    {
        $ctx = $this->resolver->resolve(Request::create('/', 'GET'), '/admin/attendances');

        $this->assertSame('admin', $ctx['portal']);
        $this->assertSame('attendance', $ctx['page_context']);
        $this->assertSame('/admin/attendances', $ctx['path']);
    }

    public function test_maps_staff_role_to_admin_portal_on_public_path(): void
    {
        $user = new User(['user_type' => 'control_staff', 'name' => 'Staff User']);
        $request = Request::create('/', 'GET');
        $request->setUserResolver(fn () => $user);

        $ctx = $this->resolver->resolve($request);

        $this->assertSame('admin', $ctx['portal']);
        $this->assertSame('control_staff', $ctx['role']);
    }
}
