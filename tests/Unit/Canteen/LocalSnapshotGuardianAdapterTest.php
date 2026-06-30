<?php

namespace Tests\Unit\Canteen;

use App\Models\User;
use App\Modules\Canteen\Exceptions\GuardianAccessDeniedException;
use App\Modules\Canteen\Integration\Adapters\LocalSnapshotGuardianAdapter;
use App\Modules\Canteen\Models\StudentProfile;
use Tests\Support\CanteenGuardianTestSchema;
use Tests\TestCase;

class LocalSnapshotGuardianAdapterTest extends TestCase
{
    use CanteenGuardianTestSchema;

    protected LocalSnapshotGuardianAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCanteenGuardianTestSchema();
        $this->adapter = new LocalSnapshotGuardianAdapter;
    }

    public function test_resolve_primary_guardian_from_profile_metadata(): void
    {
        $guardian = User::factory()->create(['user_type' => 'guardian', 'name' => 'Local Guardian']);
        $student = User::factory()->create(['user_type' => 'student']);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'student_id_ref' => (string) $student->id,
            'student_name' => $student->name,
            'guardian_id_ref' => (string) $guardian->id,
            'metadata' => [
                'guardian_name' => $guardian->name,
                'guardian_relationship_type' => 'guardian',
            ],
            'is_active' => true,
        ]);

        $resolved = $this->adapter->resolvePrimaryGuardian($student);

        $this->assertNotNull($resolved);
        $this->assertSame((string) $guardian->id, $resolved->guardianIdRef);
        $this->assertSame('Local Guardian', $resolved->guardianName);
    }

    public function test_assert_guardian_linked_rejects_mismatched_guardian(): void
    {
        $guardian = User::factory()->create(['user_type' => 'guardian']);
        $other = User::factory()->create(['user_type' => 'guardian']);
        $student = User::factory()->create(['user_type' => 'student']);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'student_id_ref' => (string) $student->id,
            'student_name' => $student->name,
            'guardian_id_ref' => (string) $guardian->id,
            'is_active' => true,
        ]);

        $this->expectException(GuardianAccessDeniedException::class);
        $this->adapter->assertGuardianLinkedToStudent($other, $student);
    }

    public function test_student_refs_for_guardian_reads_profiles(): void
    {
        $guardian = User::factory()->create(['user_type' => 'guardian']);
        $student = User::factory()->create(['user_type' => 'student']);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'student_id_ref' => (string) $student->id,
            'student_name' => $student->name,
            'guardian_id_ref' => (string) $guardian->id,
            'is_active' => true,
        ]);

        $refs = $this->adapter->studentRefsForGuardian($guardian);

        $this->assertSame([(string) $student->id], $refs);
    }
}
