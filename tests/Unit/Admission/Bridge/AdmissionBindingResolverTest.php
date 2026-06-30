<?php

namespace Tests\Unit\Admission\Bridge;

use App\Exceptions\Admission\BridgeBindingAmbiguousException;
use App\Models\Form;
use App\Services\Admission\Bridge\AdmissionBindingResolver;
use App\Support\Admission\Bridge\AdmissionBridgeConfig;
use App\Support\Admission\Bridge\BridgeErrorCode;
use Illuminate\Support\Facades\Config;
use Tests\Support\AdmissionBridgeTestSchema;
use Tests\TestCase;

class AdmissionBindingResolverTest extends TestCase
{
    use AdmissionBridgeTestSchema;

    protected function resolver(): AdmissionBindingResolver
    {
        return new AdmissionBindingResolver(new AdmissionBridgeConfig);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureBridgeTestTables();
        $this->truncateBridgeTestTables();

        Config::set('admissions_bridge.enabled', true);
    }

    public function test_resolves_enabled_binding_for_published_form(): void
    {
        $form = Form::create([
            'name' => 'Campus Visit',
            'status' => 'enable',
            'publication_status' => 'published',
            'version' => 2,
        ]);

        Config::set('admissions_intake_bindings', [
            'campus_visit_primary' => [
                'binding_key' => 'campus_visit_primary',
                'enabled' => true,
                'form_id' => $form->id,
                'mapped_form_version' => 2,
                'mapping_profile' => 'admissions_visit_v1',
                'field_map' => [
                    'contact.name' => 'fld_1',
                ],
            ],
        ]);

        $result = $this->resolver()->resolveByFormId($form->id);

        $this->assertTrue($result->isResolved());
        $this->assertSame('campus_visit_primary', $result->binding?->bindingKey);
        $this->assertSame($form->id, $result->form?->id);
    }

    public function test_returns_inactive_when_binding_disabled(): void
    {
        $form = Form::create([
            'name' => 'Campus Visit',
            'status' => 'enable',
            'publication_status' => 'published',
            'version' => 2,
        ]);

        Config::set('admissions_intake_bindings', [
            'campus_visit_primary' => [
                'binding_key' => 'campus_visit_primary',
                'enabled' => false,
                'form_id' => $form->id,
                'mapped_form_version' => 2,
                'mapping_profile' => 'admissions_visit_v1',
                'field_map' => [],
            ],
        ]);

        $result = $this->resolver()->resolveByFormId($form->id);

        $this->assertTrue($result->isNotFound());
    }

    public function test_throws_when_duplicate_enabled_bindings_share_form_id(): void
    {
        $form = Form::create([
            'name' => 'Campus Visit',
            'status' => 'enable',
            'publication_status' => 'published',
            'version' => 2,
        ]);

        Config::set('admissions_intake_bindings', [
            'binding_a' => [
                'binding_key' => 'binding_a',
                'enabled' => true,
                'form_id' => $form->id,
                'mapped_form_version' => 2,
                'mapping_profile' => 'admissions_visit_v1',
                'field_map' => [],
            ],
            'binding_b' => [
                'binding_key' => 'binding_b',
                'enabled' => true,
                'form_id' => $form->id,
                'mapped_form_version' => 2,
                'mapping_profile' => 'admissions_visit_v1',
                'field_map' => [],
            ],
        ]);

        $this->expectException(BridgeBindingAmbiguousException::class);

        $this->resolver()->resolveByFormId($form->id);
    }

    public function test_returns_not_found_when_form_id_missing_from_bindings(): void
    {
        Config::set('admissions_intake_bindings', [
            'campus_visit_primary' => [
                'binding_key' => 'campus_visit_primary',
                'enabled' => true,
                'form_id' => null,
                'mapped_form_version' => null,
                'mapping_profile' => 'admissions_visit_v1',
                'field_map' => [],
            ],
        ]);

        $result = $this->resolver()->resolveByFormId(999);

        $this->assertTrue($result->isNotFound());
        $this->assertSame(BridgeErrorCode::BINDING_NOT_FOUND, $result->errorCode);
    }

    public function test_returns_inactive_when_form_unpublished(): void
    {
        $form = Form::create([
            'name' => 'Campus Visit',
            'status' => 'enable',
            'publication_status' => 'draft',
            'version' => 2,
        ]);

        Config::set('admissions_intake_bindings', [
            'campus_visit_primary' => [
                'binding_key' => 'campus_visit_primary',
                'enabled' => true,
                'form_id' => $form->id,
                'mapped_form_version' => 2,
                'mapping_profile' => 'admissions_visit_v1',
                'field_map' => [],
            ],
        ]);

        $result = $this->resolver()->resolveByFormId($form->id);

        $this->assertTrue($result->isInactive());
        $this->assertSame('form_not_published', $result->inactiveReason);
        $this->assertSame(BridgeErrorCode::BINDING_INACTIVE, $result->errorCode);
    }

    public function test_returns_inactive_when_form_disabled(): void
    {
        $form = Form::create([
            'name' => 'Campus Visit',
            'status' => 'disable',
            'publication_status' => 'published',
            'version' => 2,
        ]);

        Config::set('admissions_intake_bindings', [
            'campus_visit_primary' => [
                'binding_key' => 'campus_visit_primary',
                'enabled' => true,
                'form_id' => $form->id,
                'mapped_form_version' => 2,
                'mapping_profile' => 'admissions_visit_v1',
                'field_map' => [],
            ],
        ]);

        $result = $this->resolver()->resolveByFormId($form->id);

        $this->assertTrue($result->isInactive());
        $this->assertSame('form_not_enabled', $result->inactiveReason);
    }

    public function test_returns_inactive_when_bridge_globally_disabled(): void
    {
        Config::set('admissions_bridge.enabled', false);

        $form = Form::create([
            'name' => 'Campus Visit',
            'status' => 'enable',
            'publication_status' => 'published',
            'version' => 2,
        ]);

        Config::set('admissions_intake_bindings', [
            'campus_visit_primary' => [
                'binding_key' => 'campus_visit_primary',
                'enabled' => true,
                'form_id' => $form->id,
                'mapped_form_version' => 2,
                'mapping_profile' => 'admissions_visit_v1',
                'field_map' => [],
            ],
        ]);

        $result = $this->resolver()->resolveByFormId($form->id);

        $this->assertTrue($result->isInactive());
        $this->assertSame('bridge_globally_disabled', $result->inactiveReason);
    }

    public function test_form_version_guard_matches_snapshot_version(): void
    {
        $guard = new \App\Support\Admission\Bridge\BridgeFormVersionGuard;

        $binding = \App\Support\Admission\Bridge\AdmissionBindingDefinition::fromConfig('campus_visit_primary', [
            'binding_key' => 'campus_visit_primary',
            'enabled' => true,
            'form_id' => 1,
            'mapped_form_version' => 3,
            'mapping_profile' => 'admissions_visit_v1',
            'field_map' => [],
        ]);

        $this->assertTrue($guard->matches($binding, 3));
        $this->assertFalse($guard->matches($binding, 2));
        $this->assertSame(BridgeErrorCode::MAP_VERSION_MISMATCH, $guard->mismatchErrorCode());
    }
}
