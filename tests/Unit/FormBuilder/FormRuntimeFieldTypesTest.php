<?php

namespace Tests\Unit\FormBuilder;

use App\Support\FormBuilder\FormRuntimeFieldTypes;
use Tests\TestCase;

class FormRuntimeFieldTypesTest extends TestCase
{
    public function test_supported_types_match_config(): void
    {
        $this->assertSame(
            config('form-builder.supported_runtime_field_types'),
            FormRuntimeFieldTypes::supported(),
        );
    }

    public function test_unsupported_education_and_advanced_types(): void
    {
        $this->assertFalse(FormRuntimeFieldTypes::isSupported('grade'));
        $this->assertFalse(FormRuntimeFieldTypes::isSupported('file'));
        $this->assertTrue(FormRuntimeFieldTypes::isSupported('text'));
        $this->assertTrue(FormRuntimeFieldTypes::isSupported('checkbox'));
    }
}
