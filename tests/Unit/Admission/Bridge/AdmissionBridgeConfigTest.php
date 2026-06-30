<?php

namespace Tests\Unit\Admission\Bridge;

use App\Support\Admission\Bridge\AdmissionBridgeConfig;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AdmissionBridgeConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('admissions_bridge', [
            'enabled' => false,
            'processing_mode' => 'queue',
            'queue_name' => 'admissions-bridge',
            'dlq_enabled' => true,
            'auto_disable_on_version_mismatch' => false,
        ]);

        Config::set('admissions_intake_bindings.campus_visit_primary', [
            'binding_key' => 'campus_visit_primary',
            'enabled' => false,
            'form_id' => null,
            'mapped_form_version' => null,
            'mapping_profile' => 'admissions_visit_v1',
            'field_map' => [],
        ]);
    }

    public function test_bridge_config_loads_with_safe_defaults(): void
    {
        $config = new AdmissionBridgeConfig;

        $this->assertFalse($config->enabled());
        $this->assertSame('queue', $config->processingMode());
        $this->assertSame('admissions-bridge', $config->queueName());
        $this->assertTrue($config->dlqEnabled());
        $this->assertFalse($config->autoDisableOnVersionMismatch());
    }

    public function test_campus_visit_binding_structure_is_valid(): void
    {
        $config = new AdmissionBridgeConfig;
        $binding = $config->binding('campus_visit_primary');

        $this->assertNotNull($binding);
        $this->assertSame('campus_visit_primary', $binding->bindingKey);
        $this->assertFalse($binding->enabled);
        $this->assertNull($binding->formId);
        $this->assertNull($binding->mappedFormVersion);
        $this->assertSame('admissions_visit_v1', $binding->mappingProfile);
        $this->assertIsArray($binding->fieldMap);
    }

    public function test_mapping_profile_has_no_fld_keys(): void
    {
        $config = new AdmissionBridgeConfig;
        $profile = $config->mappingProfile('admissions_visit_v1');

        $this->assertNotNull($profile);
        $this->assertFalse($profile->containsFieldKeys());
        $this->assertContains('contact.name', $profile->required);
        $this->assertContains('applicant.first_name', $profile->required);
        $this->assertNotEmpty($profile->requiredAny);
        $this->assertArrayHasKey('contact.phone', $profile->transforms);
    }

    public function test_binding_field_map_is_separate_from_profile(): void
    {
        $config = new AdmissionBridgeConfig;
        $binding = $config->binding('campus_visit_primary');
        $profile = $config->mappingProfile('admissions_visit_v1');

        $this->assertNotNull($binding);
        $this->assertNotNull($profile);
        $this->assertArrayNotHasKey('fld_12', $profile->transforms);
        $this->assertEmpty($binding->fieldMap);
    }
}
