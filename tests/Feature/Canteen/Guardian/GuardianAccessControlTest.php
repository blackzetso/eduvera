<?php

namespace Tests\Feature\Canteen\Guardian;

use App\Models\User;
use App\Modules\Canteen\Integration\Adapters\CoreGuardianIntegrationAdapter;
use App\Modules\Canteen\Integration\Contracts\GuardianIntegrationPort;
use Tests\Feature\Canteen\Guardian\Concerns\InteractsWithGuardianCanteenApi;
use Tests\Support\CanteenGuardianTestSchema;
use Tests\TestCase;

class GuardianAccessControlTest extends TestCase
{
    use CanteenGuardianTestSchema;
    use InteractsWithGuardianCanteenApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCanteenGuardianTestSchema();
        config(['canteen.enabled' => true]);
        $this->app->singleton(GuardianIntegrationPort::class, CoreGuardianIntegrationAdapter::class);
    }

    public function test_guardian_api_returns_404_when_canteen_module_disabled(): void
    {
        config(['canteen.enabled' => false]);
        [$student, $guardian] = $this->seedLinkedFamily();

        $this->actingAs($guardian)
            ->getJson($this->guardianApiUrl('summary'))
            ->assertNotFound();
    }

    public function test_non_guardian_cannot_access_guardian_api(): void
    {
        $student = User::factory()->create(['user_type' => 'student']);

        $this->actingAs($student)
            ->getJson($this->guardianApiUrl('summary'))
            ->assertForbidden();
    }

    public function test_guardian_cannot_access_unlinked_student_child_routes(): void
    {
        [$student] = $this->seedLinkedFamily();
        $stranger = User::factory()->create(['user_type' => 'guardian']);

        $this->actingAs($stranger)
            ->getJson($this->guardianApiUrl("children/{$student->id}/limits"))
            ->assertForbidden();
    }

    public function test_guardian_cannot_access_nonexistent_student(): void
    {
        $guardian = User::factory()->create(['user_type' => 'guardian']);

        $this->actingAs($guardian)
            ->getJson($this->guardianApiUrl('children/99999/limits'))
            ->assertNotFound();
    }

    public function test_linked_guardian_can_access_child_limits(): void
    {
        [$student, $guardian] = $this->seedLinkedFamily();

        $this->actingAs($guardian)
            ->getJson($this->guardianApiUrl("children/{$student->id}/limits"))
            ->assertOk()
            ->assertJsonPath('student_id', $student->id)
            ->assertJsonStructure([
                'student_id',
                'student_id_ref',
                'daily_limit' => ['limit', 'spent_today', 'remaining'],
                'health_restrictions',
                'guardian_purchase_blocked',
            ]);
    }
}
