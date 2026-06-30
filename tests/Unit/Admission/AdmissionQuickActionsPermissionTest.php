<?php

namespace Tests\Unit\Admission;

use App\Models\Admission\AdmissionApplicant;
use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionContact;
use App\Models\User;
use App\Services\Admission\AdmissionConversionService;
use App\Services\Admission\AdmissionDocumentService;
use App\Services\Admission\AdmissionDuplicateDetectionService;
use App\Services\Admission\AdmissionEngagementService;
use App\Services\Admission\AdmissionGuardianMatcherService;
use App\Services\Admission\AdmissionProfileService;
use App\Services\Admission\AdmissionTimelineService;
use App\Support\Admission\AdmissionDecision;
use App\Support\Admission\AdmissionReadinessPolicy;
use App\Support\Admission\AdmissionStage;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class AdmissionQuickActionsPermissionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_admissions_officer_sees_accept_but_not_convert_when_ready(): void
    {
        $actions = $this->quickActionsForRole('admissions_officer', decisionReady: true, conversionReady: false);

        $this->assertTrue($actions['accept']);
        $this->assertTrue($actions['reject']);
        $this->assertFalse($actions['convert']);
    }

    public function test_admissions_officer_cannot_convert_accepted_application(): void
    {
        $actions = $this->quickActionsForRole('admissions_officer', decisionReady: true, conversionReady: true);

        $this->assertFalse($actions['convert']);
    }

    public function test_registrar_sees_convert_but_not_accept_when_ready(): void
    {
        $actions = $this->quickActionsForRole('registrar', decisionReady: true, conversionReady: true);

        $this->assertFalse($actions['accept']);
        $this->assertFalse($actions['reject']);
        $this->assertTrue($actions['convert']);
        $this->assertTrue($actions['withdraw']);
    }

    public function test_finance_officer_sees_no_decision_actions(): void
    {
        $actions = $this->quickActionsForRole('finance_officer', decisionReady: true, conversionReady: true);

        $this->assertFalse($actions['accept']);
        $this->assertFalse($actions['reject']);
        $this->assertFalse($actions['waitlist']);
        $this->assertFalse($actions['withdraw']);
        $this->assertFalse($actions['convert']);
    }

    public function test_accept_hidden_when_decision_readiness_false(): void
    {
        $actions = $this->quickActionsForRole('admissions_officer', decisionReady: false, conversionReady: false);

        $this->assertFalse($actions['accept']);
    }

    /**
     * @return array<string, bool>
     */
    protected function quickActionsForRole(string $role, bool $decisionReady, bool $conversionReady): array
    {
        $admin = new User([
            'id' => 99,
            'user_type' => 'admin',
            'role' => $role,
        ]);
        $this->actingAs($admin);

        $documents = Mockery::mock(AdmissionDocumentService::class)->makePartial();
        $documents->shouldReceive('summaryFor')->andReturn([
            'required_total' => 1,
            'required_approved' => $decisionReady ? 1 : 0,
            'required_incomplete' => $decisionReady ? 0 : 1,
            'complete' => $decisionReady,
            'progress_percent' => $decisionReady ? 100 : 0,
        ]);

        $policy = new AdmissionReadinessPolicy($documents);
        $profile = new AdmissionProfileService(
            $documents,
            Mockery::mock(AdmissionDuplicateDetectionService::class),
            Mockery::mock(AdmissionGuardianMatcherService::class),
            Mockery::mock(AdmissionTimelineService::class),
            Mockery::mock(AdmissionEngagementService::class),
            Mockery::mock(AdmissionConversionService::class),
            $policy,
        );

        $application = $this->makeApplication([
            'pipeline_stage' => AdmissionStage::APPLICATION,
            'decision' => $conversionReady ? AdmissionDecision::ACCEPTED : null,
            'target_category_id' => 5,
        ]);

        if (! $decisionReady) {
            $application->setRelation('documents', collect());
        }

        $method = new \ReflectionMethod($profile, 'quickActions');

        return $method->invoke($profile, $application);
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
            'academic_year' => '2025-2026',
            'source_channel' => 'website',
            'target_category_id' => 5,
            'decision' => null,
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
