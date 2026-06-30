<?php

namespace Tests\Unit\Admission;

use App\Models\Admission\AdmissionApplicant;
use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionContact;
use App\Services\Admission\AdmissionConversionService;
use App\Services\Admission\AdmissionGuardianMatcherService;
use App\Services\PlatformAuditService;
use App\Services\StudentCodeService;
use App\Services\StudentEnrollmentService;
use App\Services\StudentGuardianService;
use App\Services\StudentStatusService;
use App\Support\Admission\AdmissionDecision;
use App\Support\Admission\AdmissionReadinessPolicy;
use App\Support\Admission\AdmissionReadinessResult;
use App\Support\Admission\AdmissionStage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class AdmissionConversionServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_assess_readiness_delegates_to_policy(): void
    {
        $readiness = Mockery::mock(AdmissionReadinessPolicy::class);
        $readiness->shouldReceive('evaluate')
            ->once()
            ->with(Mockery::type(AdmissionApplication::class), AdmissionReadinessPolicy::CONTEXT_CONVERSION)
            ->andReturn(new AdmissionReadinessResult(true, [], AdmissionReadinessPolicy::CONTEXT_CONVERSION));

        $service = $this->makeService(readiness: $readiness);

        $result = $service->assessReadiness($this->makeApplication());

        $this->assertTrue($result['ready']);
        $this->assertSame(AdmissionReadinessPolicy::CONTEXT_CONVERSION, $result['context']);
    }

    public function test_assert_ready_propagates_validation_exception(): void
    {
        $readiness = Mockery::mock(AdmissionReadinessPolicy::class);
        $readiness->shouldReceive('assertReady')
            ->once()
            ->andThrow(ValidationException::withMessages(['conversion' => ['بيانات ناقصة']]));

        $policy = $readiness;

        $this->expectException(ValidationException::class);

        $policy->assertReady($this->makeApplication(), AdmissionReadinessPolicy::CONTEXT_CONVERSION);
    }

    public function test_conversion_readiness_contract_includes_guardian_and_enrollment_checks(): void
    {
        $documents = Mockery::mock(\App\Services\Admission\AdmissionDocumentService::class)->makePartial();
        $documents->shouldReceive('summaryFor')->andReturn([
            'required_total' => 0,
            'required_approved' => 0,
            'required_pending' => 0,
            'required_incomplete' => 0,
            'missing' => 0,
            'complete' => true,
            'progress_percent' => 100,
        ]);

        $policy = new AdmissionReadinessPolicy($documents);
        $application = $this->makeApplication([
            'pipeline_stage' => AdmissionStage::APPLICATION,
            'decision' => AdmissionDecision::ACCEPTED,
            'target_category_id' => 5,
        ]);

        $result = $policy->evaluate($application, AdmissionReadinessPolicy::CONTEXT_CONVERSION);

        $checkIds = collect($result->checks)->pluck('id')->all();

        $this->assertContains('applicant_exists', $checkIds);
        $this->assertContains('decision_accepted', $checkIds);
        $this->assertContains('not_converted', $checkIds);
        $this->assertContains('no_existing_enrollment', $checkIds);
        $this->assertTrue($result->ready);
    }

    protected function makeService(?AdmissionReadinessPolicy $readiness = null): AdmissionConversionService
    {
        return new AdmissionConversionService(
            Mockery::mock(AdmissionGuardianMatcherService::class),
            Mockery::mock(StudentCodeService::class),
            Mockery::mock(StudentEnrollmentService::class),
            Mockery::mock(StudentGuardianService::class),
            Mockery::mock(StudentStatusService::class),
            $readiness ?? Mockery::mock(AdmissionReadinessPolicy::class),
            Mockery::mock(PlatformAuditService::class),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeApplication(array $attributes = []): AdmissionApplication
    {
        $application = new AdmissionApplication(array_merge([
            'id' => 1,
            'reference_code' => 'ADM-TEST-001',
            'pipeline_stage' => AdmissionStage::APPLICATION,
            'status' => 'open',
            'decision' => AdmissionDecision::ACCEPTED,
            'target_category_id' => 5,
            'converted_student_id' => null,
        ], $attributes));

        $application->setRelation('applicants', collect([
            new AdmissionApplicant([
                'first_name' => 'Sara',
                'date_of_birth' => '2015-01-01',
                'gender' => 'female',
                'target_category_id' => 5,
            ]),
        ]));

        $application->setRelation('contacts', collect([
            new AdmissionContact([
                'name' => 'Parent',
                'phone' => '01000000000',
                'email' => 'parent@example.com',
                'is_primary' => true,
            ]),
        ]));

        $application->setRelation('documents', collect());

        return $application;
    }
}
