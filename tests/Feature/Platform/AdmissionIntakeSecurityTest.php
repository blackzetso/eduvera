<?php

namespace Tests\Feature\Platform;

use App\Services\Admission\AdmissionIntakeGuardService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdmissionIntakeSecurityTest extends TestCase
{
    public function test_guard_rejects_honeypot_field(): void
    {
        $guard = app(AdmissionIntakeGuardService::class);
        $request = Request::create('/api/admissions/intake/visit', 'POST', [
            'parent_name' => 'Test Parent',
            '_hp_url' => 'http://spam.test',
        ]);

        $this->expectException(ValidationException::class);
        $guard->validateRequest($request);
    }

    public function test_captcha_disabled_by_default(): void
    {
        $this->assertFalse(config('admissions_intake.captcha.enabled'));
    }

    public function test_rate_limit_config_has_safe_default(): void
    {
        $this->assertGreaterThanOrEqual(5, config('admissions_intake.rate_limit_per_minute'));
        $this->assertLessThanOrEqual(60, config('admissions_intake.rate_limit_per_minute'));
    }
}
