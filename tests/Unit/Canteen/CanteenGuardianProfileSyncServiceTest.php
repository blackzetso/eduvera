<?php

namespace Tests\Unit\Canteen;

use App\Models\User;
use App\Modules\Canteen\Integration\Adapters\CoreGuardianIntegrationAdapter;
use App\Modules\Canteen\Models\StudentProfile;
use App\Modules\Canteen\Services\CanteenGuardianProfileSyncService;
use Tests\Support\CanteenGuardianTestSchema;
use Tests\TestCase;

class CanteenGuardianProfileSyncServiceTest extends TestCase
{
    use CanteenGuardianTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCanteenGuardianTestSchema();
        $this->app->singleton(\App\Modules\Canteen\Integration\Contracts\GuardianIntegrationPort::class, CoreGuardianIntegrationAdapter::class);
    }

    public function test_sync_writes_primary_guardian_fields_on_profile(): void
    {
        $student = User::factory()->create(['user_type' => 'student']);
        $guardian = User::factory()->create(['user_type' => 'guardian', 'name' => 'Synced Guardian']);
        $student->guardians()->attach($guardian->id, [
            'relationship_type' => 'father',
            'is_primary' => true,
        ]);

        $profile = StudentProfile::query()->create([
            'user_id' => $student->id,
            'student_id_ref' => (string) $student->id,
            'student_name' => $student->name,
            'is_active' => true,
        ]);

        $synced = app(CanteenGuardianProfileSyncService::class)->syncForStudent($student, $profile);

        $this->assertSame($guardian->id, $synced->primary_guardian_user_id);
        $this->assertSame((string) $guardian->id, $synced->guardian_id_ref);
        $this->assertSame((string) $guardian->id, $synced->metadata['guardian_id_ref']);
    }
}
