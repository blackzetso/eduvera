<?php

namespace Tests\Unit\Canteen;

use App\Models\User;
use App\Modules\Canteen\Models\StudentProfile;
use App\Modules\Canteen\Services\CanteenSyncAllService;
use App\Support\Student\StudentStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CanteenGuardianTestSchema;
use Tests\TestCase;

class CanteenSyncAllServiceTest extends TestCase
{
    use CanteenGuardianTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCanteenGuardianTestSchema();

        Schema::table('canteen_student_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('canteen_student_profiles', 'grade')) {
                $table->string('grade')->nullable();
            }
            if (! Schema::hasColumn('canteen_student_profiles', 'class_name')) {
                $table->string('class_name')->nullable();
            }
            if (! Schema::hasColumn('canteen_student_profiles', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable();
            }
        });
    }

    public function test_profile_needs_sync_when_enrollment_fields_differ(): void
    {
        $student = User::factory()->create([
            'user_type' => 'student',
            'student_status' => StudentStatus::ACTIVE,
            'name' => 'Current Name',
        ]);

        $student->studentEnrollments()->create([
            'academic_year' => '2025-2026',
            'grade_name' => '6',
            'class_name' => 'd',
            'enrollment_date' => now()->toDateString(),
            'status' => 'active',
            'is_current' => true,
        ]);

        $profile = StudentProfile::query()->create([
            'user_id' => $student->id,
            'student_id_ref' => (string) $student->id,
            'student_name' => 'Current Name',
            'grade' => '3',
            'class_name' => 'b',
            'is_active' => true,
        ]);

        $service = app(CanteenSyncAllService::class);

        $this->assertTrue($service->profileNeedsSync($student, $profile));

        $profile->update(['grade' => '6', 'class_name' => 'd']);
        $this->assertFalse($service->profileNeedsSync($student->fresh(), $profile->fresh()));
    }

    public function test_resolve_manager_requires_teacher_user_type(): void
    {
        $guardian = User::factory()->create(['user_type' => 'guardian']);

        $service = app(CanteenSyncAllService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not a teacher');

        $service->resolveManager(['manager_user_id' => $guardian->id]);
    }
}
