<?php

namespace Tests\Unit\Canteen;

use App\Models\StudentEnrollment;
use App\Models\User;
use App\Modules\Canteen\Integration\Adapters\CoreGuardianIntegrationAdapter;
use App\Modules\Canteen\Models\StudentProfile;
use App\Modules\Canteen\Services\CanteenStudentEligibilityService;
use Tests\Support\CanteenGuardianTestSchema;
use Tests\TestCase;

class CanteenStudentEligibilityServiceGuardianTest extends TestCase
{
    use CanteenGuardianTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'canteen.guardian.require_linked_guardian' => true,
        ]);

        $this->setUpCanteenGuardianTestSchema();
        $this->app->singleton(\App\Modules\Canteen\Integration\Contracts\GuardianIntegrationPort::class, CoreGuardianIntegrationAdapter::class);
    }

    public function test_student_without_guardian_is_blocked_when_required(): void
    {
        $student = $this->createEligibleStudent(linkGuardian: false);

        $reason = app(CanteenStudentEligibilityService::class)->purchaseBlockReason($student);

        $this->assertStringContainsString('no linked guardian', $reason);
    }

    public function test_student_with_guardian_passes_guardian_requirement(): void
    {
        $student = $this->createEligibleStudent(linkGuardian: true);

        $reason = app(CanteenStudentEligibilityService::class)->purchaseBlockReason($student);

        $this->assertNull($reason);
    }

    public function test_guardian_purchase_block_on_profile_prevents_purchase(): void
    {
        $student = $this->createEligibleStudent(linkGuardian: true);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'student_id_ref' => (string) $student->id,
            'student_name' => $student->name,
            'metadata' => ['guardian_purchase_blocked' => true],
            'is_active' => true,
        ]);

        $reason = app(CanteenStudentEligibilityService::class)->purchaseBlockReason($student);

        $this->assertStringContainsString('Guardian has blocked', $reason);
    }

    protected function createEligibleStudent(bool $linkGuardian): User
    {
        $student = User::factory()->create([
            'user_type' => 'student',
            'student_status' => 'active',
        ]);

        StudentEnrollment::query()->create([
            'student_id' => $student->id,
            'enrollment_date' => now()->toDateString(),
            'status' => 'active',
            'is_current' => true,
        ]);

        if ($linkGuardian) {
            $guardian = User::factory()->create(['user_type' => 'guardian']);
            $student->guardians()->attach($guardian->id, [
                'relationship_type' => 'guardian',
                'is_primary' => true,
            ]);
        }

        return $student;
    }
}
