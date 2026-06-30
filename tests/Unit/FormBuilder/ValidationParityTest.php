<?php

namespace Tests\Unit\FormBuilder;

use App\Services\FormBuilder\Runtime\FormValidationService;
use App\Support\FormBuilder\FormFieldDefinition;
use App\Support\FormBuilder\FormLogicEffects;
use Tests\TestCase;

class ValidationParityTest extends TestCase
{
    protected FormValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new FormValidationService;
    }

    public function test_parity_fixtures_match_expected_outcomes(): void
    {
        $fixtures = $this->loadFixtures();
        $failures = [];

        foreach ($fixtures['parity'] as $case) {
            $field = $this->fieldFromFixture($case['field']);
            $effects = $this->effectsFromFixture($case['effects'] ?? []);
            $values = $case['values'] ?? [];

            $result = $this->validator->validate([$field], $values, $effects, 'ar');
            $valid = $result->valid;

            if ($valid !== $case['expectedValid']) {
                $failures[] = sprintf(
                    '%s: expected %s, got %s',
                    $case['id'],
                    $case['expectedValid'] ? 'valid' : 'invalid',
                    $valid ? 'valid' : 'invalid',
                );
                continue;
            }

            if (! $valid && isset($case['expectedRule'])) {
                $rule = $result->errors[0]->rule ?? null;

                if ($rule !== $case['expectedRule']) {
                    $failures[] = sprintf(
                        '%s: expected rule %s, got %s',
                        $case['id'],
                        $case['expectedRule'],
                        $rule,
                    );
                }
            }
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_known_difference_fixtures_are_documented(): void
    {
        $fixtures = $this->loadFixtures();

        $this->assertNotEmpty($fixtures['known_differences']);

        foreach ($fixtures['known_differences'] as $case) {
            $this->assertArrayHasKey('id', $case);
            $this->assertArrayHasKey('note', $case);

            if (($case['documentOnly'] ?? false) === true) {
                continue;
            }

            $field = $this->fieldFromFixture($case['field']);
            $result = $this->validator->validate([$field], $case['values'] ?? [], new FormLogicEffects, 'ar');

            $this->assertSame(
                $case['phpExpectedValid'],
                $result->valid,
                "PHP outcome for {$case['id']}",
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadFixtures(): array
    {
        $path = base_path('resources/js/formRuntime/__fixtures__/validation-parity.json');

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function fieldFromFixture(array $data): FormFieldDefinition
    {
        return FormFieldDefinition::fromArray([
            'key' => $data['key'],
            'type' => $data['type'],
            'section_id' => $data['sectionId'] ?? null,
            'required' => (bool) ($data['required'] ?? false),
            'hidden' => (bool) ($data['hidden'] ?? false),
            'readonly' => (bool) ($data['readonly'] ?? false),
            'validation' => $data['validation'] ?? [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function effectsFromFixture(array $data): FormLogicEffects
    {
        return new FormLogicEffects(
            visibleByLogic: $data['visibleByLogic'] ?? [],
            hiddenByLogic: $data['hiddenByLogic'] ?? [],
            requiredByLogic: $data['requiredByLogic'] ?? [],
            skippedSections: $data['skippedSections'] ?? [],
        );
    }
}
