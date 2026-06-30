<?php

namespace Tests\Unit\Admission\Bridge;

use App\Services\Admission\Bridge\AdmissionMappingEngine;
use App\Services\Admission\Bridge\BridgeMappingTransformApplicator;
use App\Services\Admission\Bridge\BridgeMappingValidator;
use App\Support\Admission\Bridge\AdmissionBindingDefinition;
use App\Support\Admission\Bridge\AdmissionBridgeConfig;
use App\Support\FormBuilder\FormSubmissionFinalizedPayload;
use App\Support\FormBuilder\FormSubmissionSnapshot;
use App\Support\FormBuilder\FormSubmissionStatus;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdmissionMappingEngineTest extends TestCase
{
    protected function engine(): AdmissionMappingEngine
    {
        return new AdmissionMappingEngine(
            new AdmissionBridgeConfig,
            new BridgeMappingTransformApplicator,
            new BridgeMappingValidator,
        );
    }

    public function test_maps_submission_fields_to_normalized_payload(): void
    {
        $binding = $this->binding($this->fieldMap());
        $event = $this->makeEvent([
            'fld_1' => ' Parent Name ',
            'fld_2' => '01001234567',
            'fld_3' => 'Parent@Example.COM',
            'fld_4' => ' Sara ',
            'fld_5' => 'Grade 3',
            'fld_6' => '2026-07-01',
            'fld_7' => '10:30',
            'fld_8' => ' Notes ',
        ]);

        $mapped = $this->engine()->map($event, $binding);

        $this->assertTrue($mapped->isValid());
        $this->assertSame('Parent Name', $mapped->normalizedData['contact']['name']);
        $this->assertSame('+201001234567', $mapped->normalizedData['contact']['phone']);
        $this->assertSame('parent@example.com', $mapped->normalizedData['contact']['email']);
        $this->assertSame('Sara', $mapped->normalizedData['applicant']['first_name']);
        $this->assertSame('2026-07-01', $mapped->normalizedData['visit']['scheduled_date']);
        $this->assertSame('10:30', $mapped->normalizedData['visit']['scheduled_time']);
    }

    public function test_fails_when_required_fields_are_missing(): void
    {
        $binding = $this->binding($this->fieldMap());
        $event = $this->makeEvent([
            'fld_2' => '01001234567',
            'fld_3' => 'parent@example.com',
        ]);

        $mapped = $this->engine()->map($event, $binding);

        $this->assertFalse($mapped->isValid());
        $this->assertNotEmpty($mapped->validationErrors);
    }

    public function test_fails_when_field_map_is_empty(): void
    {
        $binding = $this->binding([]);
        $event = $this->makeEvent(['fld_1' => 'Parent']);

        $mapped = $this->engine()->map($event, $binding);

        $this->assertFalse($mapped->isValid());
        $this->assertTrue(collect($mapped->validationErrors)->contains(
            fn (string $error) => str_contains($error, 'field_map'),
        ));
    }

    public function test_fails_when_required_any_group_is_unsatisfied(): void
    {
        $binding = $this->binding($this->fieldMap());
        $event = $this->makeEvent([
            'fld_1' => 'Parent Name',
            'fld_4' => 'Sara',
        ]);

        $mapped = $this->engine()->map($event, $binding);

        $this->assertFalse($mapped->isValid());
        $this->assertTrue(collect($mapped->validationErrors)->contains(
            fn (string $error) => str_contains($error, 'required_any'),
        ));
    }

    /**
     * @param  array<string, string>  $fieldMap
     */
    protected function binding(array $fieldMap): AdmissionBindingDefinition
    {
        return new AdmissionBindingDefinition(
            bindingKey: 'campus_visit_primary',
            enabled: true,
            formId: 10,
            mappedFormVersion: 2,
            mappingProfile: 'admissions_visit_v1',
            fieldMap: $fieldMap,
        );
    }

    /**
     * @return array<string, string>
     */
    protected function fieldMap(): array
    {
        return [
            'contact.name' => 'fld_1',
            'contact.phone' => 'fld_2',
            'contact.email' => 'fld_3',
            'applicant.first_name' => 'fld_4',
            'applicant.current_grade_label' => 'fld_5',
            'visit.scheduled_date' => 'fld_6',
            'visit.scheduled_time' => 'fld_7',
            'visit.notes' => 'fld_8',
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    protected function makeEvent(array $fields, int $formId = 10, int $formVersion = 2): FormSubmissionFinalizedPayload
    {
        return new FormSubmissionFinalizedPayload(
            correlationId: (string) Str::uuid(),
            submissionId: 2001,
            formId: $formId,
            status: FormSubmissionStatus::SUBMITTED,
            finalizedAt: now()->toIso8601String(),
            locale: 'ar',
            channel: 'public',
            data: FormSubmissionSnapshot::attach(
                $fields,
                [
                    'form_id' => $formId,
                    'form_version' => $formVersion,
                    'snapshot_hash' => 'hash-1',
                    'captured_at' => now()->toIso8601String(),
                ],
                ['channel' => 'public'],
            ),
        );
    }
}
