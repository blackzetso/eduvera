<?php

namespace Tests\Unit\FormBuilder;

use App\Services\FormBuilder\Runtime\FormValidationService;
use App\Support\FormBuilder\FormFieldDefinition;
use App\Support\FormBuilder\FormLogicEffects;
use Tests\TestCase;

class FormValidationServiceTest extends TestCase
{
    protected FormValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new FormValidationService;
    }

    public function test_required_rule_fails_for_empty_values(): void
    {
        $result = $this->validator->validate([
            $this->field('fld_1', ['required' => true]),
        ], []);

        $this->assertFalse($result->valid);
        $this->assertSame('required', $result->errors[0]->rule);
        $this->assertSame('fld_1', $result->errors[0]->fieldKey);
    }

    public function test_min_and_max_length_rules(): void
    {
        $field = $this->field('fld_1', [
            'min_length' => 3,
            'max_length' => 5,
        ]);

        $tooShort = $this->validator->validate([$field], ['fld_1' => 'ab']);
        $tooLong = $this->validator->validate([$field], ['fld_1' => 'abcdef']);
        $ok = $this->validator->validate([$field], ['fld_1' => 'abcd']);

        $this->assertFalse($tooShort->valid);
        $this->assertSame('min_length', $tooShort->errors[0]->rule);
        $this->assertFalse($tooLong->valid);
        $this->assertSame('max_length', $tooLong->errors[0]->rule);
        $this->assertTrue($ok->valid);
    }

    public function test_min_and_max_value_rules_for_number_fields(): void
    {
        $field = $this->field('fld_2', [
            'min_value' => 10,
            'max_value' => 20,
        ], 'number');

        $this->assertFalse($this->validator->validate([$field], ['fld_2' => 5])->valid);
        $this->assertFalse($this->validator->validate([$field], ['fld_2' => 25])->valid);
        $this->assertTrue($this->validator->validate([$field], ['fld_2' => 15])->valid);
    }

    public function test_regex_rule(): void
    {
        $field = $this->field('fld_3', ['regex' => '^[A-Z]{3}$']);

        $this->assertFalse($this->validator->validate([$field], ['fld_3' => 'abc'])->valid);
        $this->assertTrue($this->validator->validate([$field], ['fld_3' => 'ABC'])->valid);
    }

    public function test_email_rule_from_flag_and_field_type(): void
    {
        $flagged = $this->field('fld_4', ['email' => true], 'text');
        $typed = $this->field('fld_5', [], 'email');

        $this->assertFalse($this->validator->validate([$flagged], ['fld_4' => 'not-an-email'])->valid);
        $this->assertFalse($this->validator->validate([$typed], ['fld_5' => 'bad@'])->valid);
        $this->assertTrue($this->validator->validate([$flagged, $typed], [
            'fld_4' => 'user@example.com',
            'fld_5' => 'user@example.com',
        ])->valid);
    }

    public function test_phone_rule_from_flag_and_field_type(): void
    {
        $flagged = $this->field('fld_6', ['phone' => true], 'text');
        $typed = $this->field('fld_7', [], 'phone');

        $this->assertFalse($this->validator->validate([$flagged], ['fld_6' => 'abc'])->valid);
        $this->assertTrue($this->validator->validate([
            $flagged,
            $typed,
        ], [
            'fld_6' => '+966501234567',
            'fld_7' => '01000000000',
        ])->valid);
    }

    public function test_logic_required_overlay_makes_optional_field_required(): void
    {
        $effects = new FormLogicEffects(requiredByLogic: ['fld_8' => true]);

        $result = $this->validator->validate([
            $this->field('fld_8', ['required' => false]),
        ], [], $effects);

        $this->assertFalse($result->valid);
        $this->assertSame('required', $result->errors[0]->rule);
    }

    public function test_hidden_and_skipped_fields_are_not_validated(): void
    {
        $effects = new FormLogicEffects(
            hiddenByLogic: ['fld_9' => true],
            skippedSections: ['sec_1' => true],
        );

        $result = $this->validator->validate([
            $this->field('fld_9', ['required' => true]),
            $this->field('fld_10', ['required' => true], sectionId: 'sec_1'),
        ], [], $effects);

        $this->assertTrue($result->valid);
    }

    public function test_static_hidden_field_is_not_validated(): void
    {
        $result = $this->validator->validate([
            FormFieldDefinition::fromArray([
                'key' => 'fld_11',
                'type' => 'text',
                'schema' => [
                    'visibility' => ['mode' => 'hidden'],
                    'validation' => ['required' => true],
                ],
            ]),
        ], []);

        $this->assertTrue($result->valid);
    }

    public function test_readonly_field_is_not_validated(): void
    {
        $result = $this->validator->validate([
            FormFieldDefinition::fromArray([
                'key' => 'fld_12',
                'type' => 'text',
                'readonly' => true,
                'validation' => ['required' => true],
            ]),
        ], []);

        $this->assertTrue($result->valid);
    }

    public function test_multi_select_empty_array_triggers_required(): void
    {
        $result = $this->validator->validate([
            $this->field('fld_13', ['required' => true], 'multi_select'),
        ], ['fld_13' => []]);

        $this->assertFalse($result->valid);
        $this->assertSame('required', $result->errors[0]->rule);
    }

    public function test_optional_empty_field_skips_downstream_rules(): void
    {
        $result = $this->validator->validate([
            $this->field('fld_14', ['min_length' => 5]),
        ], ['fld_14' => '']);

        $this->assertTrue($result->valid);
    }

    public function test_validation_stops_at_first_failing_rule_per_field(): void
    {
        $result = $this->validator->validate([
            $this->field('fld_15', [
                'min_length' => 5,
                'max_length' => 3,
            ]),
        ], ['fld_15' => 'ab']);

        $this->assertCount(1, $result->errors);
        $this->assertSame('min_length', $result->errors[0]->rule);
    }

    public function test_result_serializes_localized_errors(): void
    {
        $result = $this->validator->validate([
            $this->field('fld_16', ['required' => true]),
        ], []);

        $ar = $result->toArray('ar');
        $en = $result->toArray('en');

        $this->assertSame('هذا الحقل مطلوب', $ar['errors'][0]['message']);
        $this->assertSame('This field is required', $en['errors'][0]['message']);
    }

    /**
     * @param  array<string, mixed>  $validation
     */
    protected function field(
        string $key,
        array $validation = [],
        string $type = 'text',
        ?string $sectionId = null,
    ): FormFieldDefinition {
        return FormFieldDefinition::fromArray([
            'key' => $key,
            'type' => $type,
            'section_id' => $sectionId,
            'validation' => $validation,
        ]);
    }
}
