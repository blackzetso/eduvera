<?php

namespace Tests\Unit\Admission;

use App\Models\Admission\AdmissionApplicant;
use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionContact;
use App\Services\Admission\AdmissionDocumentService;
use App\Services\Admission\AdmissionDuplicateDetectionService;
use App\Services\Admission\AdmissionGuardianMatcherService;
use App\Services\Admission\AdmissionProfileService;
use App\Services\Admission\AdmissionTimelineService;
use App\Services\Admission\AdmissionConversionService;
use App\Support\Admission\AdmissionDecision;
use App\Support\Admission\AdmissionReadinessPolicy;
use App\Support\Admission\AdmissionStage;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class AdmissionReadinessPolicyTest extends TestCase
{
    protected AdmissionDocumentService $documents;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_visit_schedule_context_requires_contact_and_applicant_basics(): void
    {
        $policy = $this->makePolicy();
        $application = $this->makeApplication();

        $result = $policy->evaluate($application, AdmissionReadinessPolicy::CONTEXT_VISIT_SCHEDULE);

        $this->assertFalse($result->ready);
        $this->assertContains('primary_contact_exists', collect($result->checks)->pluck('id')->all());

        $application = $this->makeApplication(
            applicants: collect([$this->makeApplicant(['first_name' => 'Sara'])]),
            contacts: collect([$this->makeContact(['name' => 'Parent', 'phone' => '01000000000'])]),
        );

        $ready = $policy->evaluate($application, AdmissionReadinessPolicy::CONTEXT_VISIT_SCHEDULE);

        $this->assertTrue($ready->ready);
        $this->assertSame(100, $ready->completionPercentage());
    }

    public function test_lead_context_allows_missing_dob_gender_when_prospect_data_complete(): void
    {
        $policy = $this->makePolicy();
        $application = $this->makeApplication(
            ['source_channel' => 'website_visit'],
            collect([$this->makeApplicant([
                'first_name' => 'Sara',
                'current_grade_label' => 'Grade 3',
            ])]),
            collect([$this->makeContact([
                'name' => 'Parent',
                'phone' => '01000000000',
                'email' => 'parent@example.com',
            ])]),
        );

        $result = $policy->evaluate($application, AdmissionReadinessPolicy::CONTEXT_LEAD);

        $this->assertTrue($result->ready);
        $this->assertSame('lead', $result->context);
    }

    public function test_lead_context_accepts_target_category_as_interest_grade(): void
    {
        $policy = $this->makePolicy();
        $application = $this->makeApplication(
            ['source_channel' => 'website_visit', 'target_category_id' => 5],
            collect([$this->makeApplicant(['first_name' => 'Sara'])]),
            collect([$this->makeContact([
                'name' => 'Parent',
                'phone' => '01000000000',
                'email' => 'parent@example.com',
            ])]),
        );

        $result = $policy->evaluate($application, AdmissionReadinessPolicy::CONTEXT_LEAD);

        $this->assertTrue($result->ready);
        $this->assertTrue(collect($result->checks)->firstWhere('id', 'interest_grade')['ok']);
    }

    public function test_lead_context_requires_interest_grade(): void
    {
        $policy = $this->makePolicy();
        $application = $this->makeApplication(
            ['source_channel' => 'website_visit'],
            collect([$this->makeApplicant(['first_name' => 'Sara'])]),
            collect([$this->makeContact([
                'name' => 'Parent',
                'phone' => '01000000000',
                'email' => 'parent@example.com',
            ])]),
        );

        $result = $policy->evaluate($application, AdmissionReadinessPolicy::CONTEXT_LEAD);

        $this->assertFalse($result->ready);
        $this->assertContains('الصف/الفئة المستهدفة (اهتمام)', $result->blockingErrors());
    }

    public function test_application_context_requires_full_application_profile(): void
    {
        $policy = $this->makePolicy();
        $application = $this->makeApplication(
            applicants: collect([$this->makeApplicant(['first_name' => 'Sara'])]),
            contacts: collect([$this->makeContact(['name' => 'Parent', 'phone' => '01000000000'])]),
        );

        $result = $policy->evaluate($application, AdmissionReadinessPolicy::CONTEXT_APPLICATION);

        $this->assertFalse($result->ready);
        $this->assertNotEmpty($result->blockingErrors());

        $application = $this->makeApplication(
            ['target_category_id' => 5],
            collect([$this->makeApplicant([
                'first_name' => 'Sara',
                'date_of_birth' => '2015-01-01',
                'gender' => 'female',
                'target_category_id' => 5,
            ])]),
            collect([$this->makeContact([
                'name' => 'Parent',
                'phone' => '01000000000',
                'email' => 'parent@example.com',
            ])]),
        );

        $ready = $policy->evaluate($application, AdmissionReadinessPolicy::CONTEXT_APPLICATION);

        $this->assertTrue($ready->ready);
    }

    public function test_decision_context_requires_application_and_documents(): void
    {
        $this->documents->shouldReceive('summaryFor')->andReturn([
            'required_total' => 1,
            'required_approved' => 0,
            'required_pending' => 1,
            'required_incomplete' => 1,
            'complete' => false,
            'missing' => 0,
            'progress_percent' => 0,
        ]);

        $policy = $this->makePolicy();
        $application = $this->makeReadyApplication();

        $result = $policy->evaluate($application, AdmissionReadinessPolicy::CONTEXT_DECISION);

        $this->assertFalse($result->ready);
        $this->assertContains('المستندات: 0 / 1 معتمد', $result->blockingErrors());

        $this->documents = Mockery::mock(AdmissionDocumentService::class)->makePartial();
        $this->documents->shouldReceive('summaryFor')->andReturn([
            'required_total' => 1,
            'required_approved' => 1,
            'required_pending' => 0,
            'required_incomplete' => 0,
            'missing' => 0,
            'complete' => true,
            'progress_percent' => 100,
        ]);
        $policy = $this->makePolicy();

        $ready = $policy->evaluate($application, AdmissionReadinessPolicy::CONTEXT_DECISION);

        $this->assertTrue($ready->ready);
    }

    public function test_conversion_context_preserves_existing_conversion_rules(): void
    {
        $this->documents->shouldReceive('summaryFor')->andReturn([
            'required_pending' => 0,
            'required_incomplete' => 0,
            'missing' => 0,
            'complete' => true,
        ]);

        $policy = $this->makePolicy();
        $application = $this->makeReadyApplication([
            'pipeline_stage' => AdmissionStage::APPLICATION,
            'decision' => AdmissionDecision::ACCEPTED,
        ]);

        $result = $policy->evaluate($application, AdmissionReadinessPolicy::CONTEXT_CONVERSION);

        $this->assertTrue($result->ready);
        $this->assertSame('conversion', $result->context);

        $blocked = $policy->evaluate(
            $this->makeReadyApplication([
                'pipeline_stage' => AdmissionStage::APPLICATION,
                'decision' => AdmissionDecision::WAITLISTED,
            ]),
            AdmissionReadinessPolicy::CONTEXT_CONVERSION,
        );

        $this->assertFalse($blocked->ready);
        $this->assertContains('قرار القبول: مقبول', $blocked->blockingErrors());
    }

    public function test_documents_are_warning_only_for_conversion_by_default(): void
    {
        config(['admissions.readiness.documents_required_for_conversion' => false]);

        $this->documents->shouldReceive('summaryFor')->andReturn([
            'required_total' => 1,
            'required_approved' => 0,
            'required_pending' => 1,
            'required_incomplete' => 1,
            'complete' => false,
            'missing' => 0,
            'progress_percent' => 0,
        ]);

        $policy = $this->makePolicy();
        $application = $this->makeReadyApplication([
            'pipeline_stage' => AdmissionStage::APPLICATION,
            'decision' => AdmissionDecision::ACCEPTED,
        ]);

        $result = $policy->evaluate($application, AdmissionReadinessPolicy::CONTEXT_CONVERSION);

        $this->assertTrue($result->ready);
        $this->assertContains('المستندات: 0 / 1 معتمد', $result->warnings());

        $documentsCheck = collect($result->checks)->firstWhere('id', 'documents_complete');
        $this->assertFalse($documentsCheck['ok']);
        $this->assertFalse($documentsCheck['blocking']);
    }

    public function test_documents_block_conversion_when_config_enabled(): void
    {
        config(['admissions.readiness.documents_required_for_conversion' => true]);

        $this->documents->shouldReceive('summaryFor')->andReturn([
            'required_total' => 1,
            'required_approved' => 0,
            'required_pending' => 1,
            'required_incomplete' => 1,
            'complete' => false,
            'missing' => 0,
            'progress_percent' => 0,
        ]);

        $policy = $this->makePolicy();
        $application = $this->makeReadyApplication([
            'pipeline_stage' => AdmissionStage::APPLICATION,
            'decision' => AdmissionDecision::ACCEPTED,
        ]);

        $result = $policy->evaluate($application, AdmissionReadinessPolicy::CONTEXT_CONVERSION);

        $this->assertFalse($result->ready);
        $this->assertContains('المستندات: 0 / 1 معتمد', $result->blockingErrors());
    }

    public function test_assert_ready_throws_when_conversion_readiness_fails(): void
    {
        $policy = $this->makePolicy();
        $application = $this->makeApplication([
            'pipeline_stage' => AdmissionStage::APPLICATION,
            'decision' => AdmissionDecision::ACCEPTED,
        ]);

        $this->expectException(ValidationException::class);

        $policy->assertReady($application, AdmissionReadinessPolicy::CONTEXT_CONVERSION);
    }

    public function test_profile_service_quick_actions_accept_matches_decision_readiness(): void
    {
        $this->actingAs($this->makeSuperAdmin());

        $this->documents->shouldReceive('summaryFor')->andReturn([
            'required_total' => 1,
            'required_approved' => 1,
            'required_pending' => 0,
            'required_incomplete' => 0,
            'missing' => 0,
            'complete' => true,
            'progress_percent' => 100,
        ]);

        $application = $this->makeReadyApplication([
            'pipeline_stage' => AdmissionStage::APPLICATION,
            'decision' => null,
        ]);

        $policy = $this->makePolicy();
        $profile = new AdmissionProfileService(
            $this->documents,
            Mockery::mock(AdmissionDuplicateDetectionService::class),
            Mockery::mock(AdmissionGuardianMatcherService::class),
            Mockery::mock(AdmissionTimelineService::class),
            Mockery::mock(\App\Services\Admission\AdmissionEngagementService::class),
            Mockery::mock(AdmissionConversionService::class),
            $policy,
        );

        $quickActions = (new \ReflectionMethod($profile, 'quickActions'))->invoke($profile, $application);
        $readiness = $policy->evaluate($application, AdmissionReadinessPolicy::CONTEXT_DECISION);

        $this->assertTrue($readiness->ready);
        $this->assertTrue($quickActions['accept']);
        $this->assertSame($readiness->ready, $quickActions['accept']);
    }

    public function test_decision_service_accept_enforces_readiness(): void
    {
        $this->documents->shouldReceive('summaryFor')->andReturn([
            'required_total' => 1,
            'required_approved' => 0,
            'required_pending' => 1,
            'required_incomplete' => 1,
            'complete' => false,
            'missing' => 0,
            'progress_percent' => 0,
        ]);

        $policy = $this->makePolicy();
        $audit = Mockery::mock(\App\Services\PlatformAuditService::class);
        $service = new \App\Services\Admission\AdmissionDecisionService($policy, $audit);
        $application = $this->makeReadyApplication(['decision' => null]);

        $this->expectException(ValidationException::class);
        $service->accept($application);
    }

    public function test_profile_service_quick_actions_convert_matches_conversion_readiness(): void
    {
        $this->actingAs($this->makeSuperAdmin());

        $application = $this->makeReadyApplication([
            'pipeline_stage' => AdmissionStage::APPLICATION,
            'decision' => AdmissionDecision::ACCEPTED,
        ]);

        $policy = $this->makePolicy();
        $profile = new AdmissionProfileService(
            $this->documents,
            Mockery::mock(AdmissionDuplicateDetectionService::class),
            Mockery::mock(AdmissionGuardianMatcherService::class),
            Mockery::mock(AdmissionTimelineService::class),
            Mockery::mock(\App\Services\Admission\AdmissionEngagementService::class),
            Mockery::mock(AdmissionConversionService::class),
            $policy,
        );

        $quickActions = (new \ReflectionMethod($profile, 'quickActions'))->invoke($profile, $application);
        $readiness = $policy->evaluate($application, AdmissionReadinessPolicy::CONTEXT_CONVERSION);

        $this->assertTrue($readiness->ready);
        $this->assertTrue($quickActions['convert']);
        $this->assertSame($readiness->ready, $quickActions['convert']);
    }

    public function test_profile_service_hides_convert_when_readiness_false(): void
    {
        $this->actingAs($this->makeSuperAdmin());

        $application = $this->makeReadyApplication([
            'pipeline_stage' => AdmissionStage::APPLICATION,
            'decision' => AdmissionDecision::WAITLISTED,
        ]);

        $policy = $this->makePolicy();
        $profile = new AdmissionProfileService(
            $this->documents,
            Mockery::mock(AdmissionDuplicateDetectionService::class),
            Mockery::mock(AdmissionGuardianMatcherService::class),
            Mockery::mock(AdmissionTimelineService::class),
            Mockery::mock(\App\Services\Admission\AdmissionEngagementService::class),
            Mockery::mock(AdmissionConversionService::class),
            $policy,
        );

        $quickActions = (new \ReflectionMethod($profile, 'quickActions'))->invoke($profile, $application);
        $readiness = $policy->evaluate($application, AdmissionReadinessPolicy::CONTEXT_CONVERSION);

        $this->assertFalse($readiness->ready);
        $this->assertFalse($quickActions['convert']);
    }

    public function test_conversion_blocked_when_enrollment_already_exists(): void
    {
        $this->documents->shouldReceive('summaryFor')->andReturn([
            'required_pending' => 0,
            'required_incomplete' => 0,
            'missing' => 0,
            'complete' => true,
        ]);

        $policy = new class($this->documents) extends AdmissionReadinessPolicy
        {
            protected function hasExistingEnrollment(AdmissionApplication $application): bool
            {
                return true;
            }
        };

        $application = $this->makeReadyApplication([
            'id' => 42,
            'pipeline_stage' => AdmissionStage::APPLICATION,
            'decision' => AdmissionDecision::ACCEPTED,
        ]);

        $result = $policy->evaluate($application, AdmissionReadinessPolicy::CONTEXT_CONVERSION);

        $this->assertFalse($result->ready);
        $this->assertContains('لا يوجد قيد مسجل مسبقاً', $result->blockingErrors());
    }

    public function test_result_array_matches_backend_contract(): void
    {
        $this->documents->shouldReceive('summaryFor')->andReturn([
            'required_pending' => 0,
            'required_incomplete' => 0,
            'missing' => 0,
            'complete' => true,
        ]);

        $policy = $this->makePolicy();
        $result = $policy->evaluate(
            $this->makeReadyApplication([
                'pipeline_stage' => AdmissionStage::APPLICATION,
                'decision' => AdmissionDecision::ACCEPTED,
            ]),
            AdmissionReadinessPolicy::CONTEXT_CONVERSION,
        )->toArray();

        $this->assertArrayHasKey('ready', $result);
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('blocking_errors', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertArrayHasKey('completion_percentage', $result);
        $this->assertArrayHasKey('context', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertContains(AdmissionReadinessPolicy::CONTEXT_LEAD, $result['contexts']);

        foreach ($result['checks'] as $check) {
            $this->assertArrayHasKey('id', $check);
            $this->assertArrayHasKey('label', $check);
            $this->assertArrayHasKey('ok', $check);
            $this->assertArrayHasKey('blocking', $check);
            $this->assertArrayHasKey('severity', $check);
        }
    }

    protected function makePolicy(): AdmissionReadinessPolicy
    {
        return new AdmissionReadinessPolicy($this->documents);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeApplication(
        array $attributes = [],
        ?Collection $applicants = null,
        ?Collection $contacts = null,
    ): AdmissionApplication {
        $application = new AdmissionApplication(array_merge([
            'id' => 1,
            'reference_code' => 'ADM-TEST-001',
            'pipeline_stage' => AdmissionStage::INQUIRY,
            'status' => 'open',
            'academic_year' => '2025-2026',
            'source_channel' => 'website',
            'target_category_id' => null,
            'decision' => null,
            'converted_student_id' => null,
        ], $attributes));

        $application->setRelation('applicants', $applicants ?? collect());
        $application->setRelation('contacts', $contacts ?? collect());
        $application->setRelation('documents', collect());

        return $application;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeReadyApplication(array $overrides = []): AdmissionApplication
    {
        return $this->makeApplication(
            array_merge(['target_category_id' => 5], $overrides),
            collect([$this->makeApplicant([
                'first_name' => 'Sara',
                'date_of_birth' => '2015-01-01',
                'gender' => 'female',
                'target_category_id' => 5,
            ])]),
            collect([$this->makeContact([
                'name' => 'Parent Name',
                'phone' => '01000000000',
                'email' => 'parent@example.com',
                'is_primary' => true,
            ])]),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeApplicant(array $attributes = []): AdmissionApplicant
    {
        return new AdmissionApplicant(array_merge([
            'first_name' => 'Applicant',
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeContact(array $attributes = []): AdmissionContact
    {
        return new AdmissionContact(array_merge([
            'name' => 'Contact',
            'is_primary' => true,
        ], $attributes));
    }

    protected function makeSuperAdmin(): \App\Models\User
    {
        return new \App\Models\User([
            'id' => 1,
            'user_type' => 'admin',
            'role' => 'super_admin',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->documents = Mockery::mock(AdmissionDocumentService::class)->makePartial();
        $this->documents->shouldReceive('summaryFor')->byDefault()->andReturn([
            'required_total' => 0,
            'required_approved' => 0,
            'required_pending' => 0,
            'required_incomplete' => 0,
            'missing' => 0,
            'complete' => true,
            'progress_percent' => 100,
        ]);
    }
}
