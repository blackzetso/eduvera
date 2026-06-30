<?php

namespace Tests\Unit\Canteen;

use App\Models\User;
use App\Modules\Canteen\Exceptions\GuardianAccessDeniedException;
use App\Modules\Canteen\Integration\Adapters\CoreGuardianIntegrationAdapter;
use Tests\Support\CanteenGuardianTestSchema;
use Tests\TestCase;

class CoreGuardianIntegrationAdapterTest extends TestCase
{
    use CanteenGuardianTestSchema;

    protected CoreGuardianIntegrationAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCanteenGuardianTestSchema();
        $this->adapter = new CoreGuardianIntegrationAdapter;
    }

    public function test_resolve_primary_guardian_prefers_primary_flag(): void
    {
        [$student, $primary, $secondary] = $this->seedFamily();

        $resolved = $this->adapter->resolvePrimaryGuardian($student);

        $this->assertNotNull($resolved);
        $this->assertSame((string) $primary->id, $resolved->guardianIdRef);
        $this->assertTrue($resolved->isPrimary);
    }

    public function test_family_context_includes_siblings(): void
    {
        [$student, $guardian] = $this->seedFamily();
        $sibling = User::factory()->create(['user_type' => 'student', 'name' => 'Sibling One']);
        $sibling->guardians()->attach($guardian->id, [
            'relationship_type' => 'guardian',
            'is_primary' => false,
        ]);

        $family = $this->adapter->familyContextForStudent($student);

        $this->assertCount(1, $family->siblings);
        $this->assertSame((string) $sibling->id, $family->siblings[0]->studentIdRef);
    }

    public function test_assert_guardian_linked_to_student_rejects_unlinked_guardian(): void
    {
        [$student] = $this->seedFamily();
        $stranger = User::factory()->create(['user_type' => 'guardian']);

        $this->expectException(GuardianAccessDeniedException::class);
        $this->adapter->assertGuardianLinkedToStudent($stranger, $student);
    }

    public function test_student_refs_for_guardian_returns_linked_children(): void
    {
        [$student, $guardian] = $this->seedFamily();

        $refs = $this->adapter->studentRefsForGuardian($guardian);

        $this->assertSame([(string) $student->id], $refs);
    }

    /**
     * @return array{0: User, 1: User, 2: User}
     */
    protected function seedFamily(): array
    {
        $student = User::factory()->create(['user_type' => 'student']);
        $primary = User::factory()->create(['user_type' => 'guardian', 'name' => 'Primary Guardian']);
        $secondary = User::factory()->create(['user_type' => 'guardian', 'name' => 'Secondary Guardian']);

        $student->guardians()->attach($primary->id, [
            'relationship_type' => 'father',
            'is_primary' => true,
            'is_financial_responsible' => true,
        ]);
        $student->guardians()->attach($secondary->id, [
            'relationship_type' => 'mother',
            'is_primary' => false,
        ]);

        return [$student, $primary, $secondary];
    }
}
