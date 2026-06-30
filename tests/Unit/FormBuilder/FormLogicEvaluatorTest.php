<?php

namespace Tests\Unit\FormBuilder;

use App\Services\FormBuilder\Runtime\FormLogicEvaluator;
use Tests\TestCase;

class FormLogicEvaluatorTest extends TestCase
{
    protected FormLogicEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->evaluator = new FormLogicEvaluator;
    }

    public function test_show_rule_makes_target_visible_when_condition_matches(): void
    {
        $effects = $this->evaluator->evaluate(
            rules: [[
                'field_key' => 'fld_1',
                'operator' => 'equals',
                'value' => 'teacher',
                'action' => 'show',
                'target_field_key' => 'fld_2',
            ]],
            values: ['fld_1' => 'teacher'],
            sectionFieldIndex: [],
            allFieldKeys: ['fld_1', 'fld_2'],
        );

        $this->assertTrue($effects->visibleByLogic['fld_2'] ?? false);
        $this->assertTrue($effects->isFieldVisibleByLogic('fld_2'));
    }

    public function test_hide_overrides_show_for_same_target(): void
    {
        $effects = $this->evaluator->evaluate(
            rules: [
                [
                    'field_key' => 'fld_1',
                    'operator' => 'equals',
                    'value' => 'yes',
                    'action' => 'show',
                    'target_field_key' => 'fld_2',
                ],
                [
                    'field_key' => 'fld_1',
                    'operator' => 'equals',
                    'value' => 'yes',
                    'action' => 'hide',
                    'target_field_key' => 'fld_2',
                ],
            ],
            values: ['fld_1' => 'yes'],
            sectionFieldIndex: [],
            allFieldKeys: ['fld_1', 'fld_2'],
        );

        $this->assertTrue($effects->hiddenByLogic['fld_2'] ?? false);
        $this->assertFalse($effects->isFieldVisibleByLogic('fld_2'));
    }

    public function test_require_rule_marks_target_as_logic_required(): void
    {
        $effects = $this->evaluator->evaluate(
            rules: [[
                'field_key' => 'fld_1',
                'operator' => 'equals',
                'value' => 'admin',
                'action' => 'require',
                'target_field_key' => 'fld_3',
            ]],
            values: ['fld_1' => 'admin'],
            sectionFieldIndex: [],
            allFieldKeys: ['fld_1', 'fld_3'],
        );

        $this->assertTrue($effects->isLogicRequired('fld_3'));
    }

    public function test_skip_section_hides_all_section_fields(): void
    {
        $effects = $this->evaluator->evaluate(
            rules: [[
                'field_key' => 'fld_1',
                'operator' => 'equals',
                'value' => 'skip',
                'action' => 'skip_section',
                'target_section_id' => 'sec_2',
            ]],
            values: ['fld_1' => 'skip'],
            sectionFieldIndex: [
                'sec_2' => ['fld_4', 'fld_5'],
            ],
            allFieldKeys: ['fld_1', 'fld_4', 'fld_5'],
        );

        $this->assertTrue($effects->isSectionSkipped('sec_2'));
        $this->assertFalse($effects->isFieldVisibleByLogic('fld_4'));
        $this->assertFalse($effects->isFieldVisibleByLogic('fld_5'));
    }

    public function test_skip_section_can_target_section_via_sec_prefix_in_field_key(): void
    {
        $effects = $this->evaluator->evaluate(
            rules: [[
                'field_key' => 'fld_1',
                'operator' => 'equals',
                'value' => '1',
                'action' => 'skip_section',
                'target_field_key' => 'sec_9',
            ]],
            values: ['fld_1' => '1'],
            sectionFieldIndex: [
                'sec_9' => ['fld_10'],
            ],
            allFieldKeys: ['fld_1', 'fld_10'],
        );

        $this->assertTrue($effects->isSectionSkipped('sec_9'));
    }

    public function test_not_equals_operator(): void
    {
        $this->assertTrue($this->evaluator->isConditionMet([
            'field_key' => 'fld_1',
            'operator' => 'not_equals',
            'value' => 'teacher',
        ], ['fld_1' => 'admin']));

        $this->assertFalse($this->evaluator->isConditionMet([
            'field_key' => 'fld_1',
            'operator' => 'not_equals',
            'value' => 'teacher',
        ], ['fld_1' => 'teacher']));
    }

    public function test_contains_operator_for_string_and_array_values(): void
    {
        $this->assertTrue($this->evaluator->isConditionMet([
            'field_key' => 'fld_1',
            'operator' => 'contains',
            'value' => '@school',
        ], ['fld_1' => 'parent@school.edu']));

        $this->assertTrue($this->evaluator->isConditionMet([
            'field_key' => 'fld_1',
            'operator' => 'contains',
            'value' => 'math',
        ], ['fld_1' => ['science', 'math']]));
    }

    public function test_rules_with_empty_field_key_are_ignored(): void
    {
        $effects = $this->evaluator->evaluate(
            rules: [[
                'field_key' => '',
                'operator' => 'equals',
                'value' => 'x',
                'action' => 'hide',
                'target_field_key' => 'fld_2',
            ]],
            values: [],
            sectionFieldIndex: [],
            allFieldKeys: ['fld_2'],
        );

        $this->assertFalse($effects->hiddenByLogic['fld_2'] ?? false);
    }

    public function test_evaluator_is_stable_across_fixed_point_passes(): void
    {
        $rules = [[
            'field_key' => 'fld_1',
            'operator' => 'equals',
            'value' => 'a',
            'action' => 'show',
            'target_field_key' => 'fld_2',
        ]];

        $first = $this->evaluator->evaluate($rules, ['fld_1' => 'a'], [], ['fld_1', 'fld_2']);
        $second = $this->evaluator->evaluate($rules, ['fld_1' => 'a'], [], ['fld_1', 'fld_2']);

        $this->assertTrue($first->equals($second));
    }
}
