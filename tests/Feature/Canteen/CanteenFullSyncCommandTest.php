<?php

namespace Tests\Feature\Canteen;

use App\Models\User;
use App\Modules\Canteen\Integration\Adapters\CoreGuardianIntegrationAdapter;
use App\Modules\Canteen\Models\Staff;
use App\Modules\Canteen\Models\StudentProfile;
use App\Modules\Canteen\Services\CanteenSyncAllService;
use App\Support\Student\StudentStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CanteenGuardianTestSchema;
use Tests\TestCase;

class CanteenFullSyncCommandTest extends TestCase
{
    use CanteenGuardianTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCanteenGuardianTestSchema();
        $this->ensureFullSyncSchemaColumns();

        config([
            'canteen.enabled' => true,
            'canteen.integration.guardian_adapter' => 'core',
            'canteen.full_sync.staff' => [],
            'canteen.full_sync.staff_env' => '',
        ]);

        $this->app->singleton(
            \App\Modules\Canteen\Integration\Contracts\GuardianIntegrationPort::class,
            CoreGuardianIntegrationAdapter::class,
        );
    }

    public function test_creates_profiles_for_new_students_and_registers_staff(): void
    {
        $student = User::factory()->create([
            'user_type' => 'student',
            'student_status' => StudentStatus::ACTIVE,
            'name' => 'New Student',
        ]);
        $student->studentEnrollments()->create([
            'academic_year' => '2025-2026',
            'grade_name' => '4',
            'class_name' => 'a',
            'enrollment_date' => now()->toDateString(),
            'status' => 'active',
            'is_current' => true,
        ]);

        $teacher = User::factory()->create(['user_type' => 'teacher', 'email' => 'pos.manager@school.test']);

        $exit = Artisan::call('canteen:full-sync', [
            '--staff' => ["{$teacher->id}:manager"],
        ]);

        $this->assertSame(0, $exit);

        $profile = StudentProfile::query()->where('user_id', $student->id)->first();
        $this->assertNotNull($profile);
        $this->assertSame((string) $student->id, $profile->student_id_ref);
        $this->assertSame('New Student', $profile->student_name);
        $this->assertIsArray($profile->health_restrictions);
        $this->assertFalse($profile->health_restrictions['block_all_purchases']);

        $staff = Staff::query()->where('user_id', $teacher->id)->first();
        $this->assertNotNull($staff);
        $this->assertSame('manager', $staff->role);
        $this->assertTrue($staff->is_active);
    }

    public function test_syncs_guardian_links_for_profiles_missing_primary_guardian(): void
    {
        $student = User::factory()->create(['user_type' => 'student', 'student_status' => StudentStatus::ACTIVE]);
        $student->studentEnrollments()->create([
            'academic_year' => '2025-2026',
            'grade_name' => '3',
            'class_name' => 'b',
            'enrollment_date' => now()->toDateString(),
            'status' => 'active',
            'is_current' => true,
        ]);
        $guardian = User::factory()->create(['user_type' => 'guardian']);
        $student->guardians()->attach($guardian->id, ['relationship_type' => 'father', 'is_primary' => true]);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'student_id_ref' => (string) $student->id,
            'student_name' => $student->name,
            'is_active' => true,
        ]);

        Artisan::call('canteen:full-sync', ['--skip-staff' => true]);

        $profile = StudentProfile::query()->where('user_id', $student->id)->first();
        $this->assertSame($guardian->id, $profile->primary_guardian_user_id);
    }

    public function test_command_is_idempotent(): void
    {
        $student = User::factory()->create(['user_type' => 'student', 'student_status' => StudentStatus::ACTIVE]);
        $student->studentEnrollments()->create([
            'academic_year' => '2025-2026',
            'grade_name' => '3',
            'class_name' => 'b',
            'enrollment_date' => now()->toDateString(),
            'status' => 'active',
            'is_current' => true,
        ]);

        $service = app(CanteenSyncAllService::class);

        $first = $service->run(['skip_staff' => true]);
        $second = $service->run(['skip_staff' => true]);

        $this->assertSame(1, $first['profiles_created']);
        $this->assertSame(0, $second['profiles_created']);
        $this->assertSame(1, StudentProfile::query()->where('user_id', $student->id)->count());
    }

    public function test_dry_run_does_not_persist_changes(): void
    {
        $student = User::factory()->create(['user_type' => 'student', 'student_status' => StudentStatus::ACTIVE]);
        $student->studentEnrollments()->create([
            'academic_year' => '2025-2026',
            'grade_name' => '2',
            'class_name' => 'c',
            'enrollment_date' => now()->toDateString(),
            'status' => 'active',
            'is_current' => true,
        ]);

        Artisan::call('canteen:full-sync', ['--dry-run' => true, '--skip-staff' => true]);

        $this->assertNull(StudentProfile::query()->where('user_id', $student->id)->first());
    }

    protected function ensureFullSyncSchemaColumns(): void
    {
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

        if (! Schema::hasTable('canteen_staff')) {
            Schema::create('canteen_staff', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('role');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('canteen_settings')) {
            Schema::create('canteen_settings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('key')->unique();
                $table->json('value')->nullable();
                $table->timestamps();
            });
        }
    }
}
