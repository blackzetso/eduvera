<?php

namespace Tests\Feature\Canteen;

use App\Models\User;
use App\Modules\Canteen\Integration\Adapters\CoreGuardianIntegrationAdapter;
use App\Modules\Canteen\Models\Setting;
use App\Modules\Canteen\Models\Staff;
use App\Modules\Canteen\Models\StudentProfile;
use App\Modules\Canteen\Services\CanteenSyncAllService;
use App\Modules\Canteen\Support\CanteenSettingKeys;
use App\Support\Student\StudentStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CanteenGuardianTestSchema;
use Tests\TestCase;

class CanteenSyncAllCommandTest extends TestCase
{
    use CanteenGuardianTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCanteenGuardianTestSchema();
        $this->ensureSyncAllSchemaColumns();

        config([
            'canteen.enabled' => true,
            'canteen.integration.guardian_adapter' => 'core',
            'canteen.full_sync.staff' => [],
            'canteen.full_sync.staff_env' => '',
            'canteen.teacher_staff.roles_env' => '',
        ]);

        $this->app->singleton(
            \App\Modules\Canteen\Integration\Contracts\GuardianIntegrationPort::class,
            CoreGuardianIntegrationAdapter::class,
        );
    }

    public function test_creates_profile_for_active_enrolled_student_without_profile(): void
    {
        $student = $this->makeActiveStudent('New Student', '4', 'a');

        Artisan::call('canteen:sync-all', ['--skip-staff' => true]);

        $profile = StudentProfile::query()->where('user_id', $student->id)->first();
        $this->assertNotNull($profile);
        $this->assertSame((string) $student->id, $profile->student_id_ref);
        $this->assertSame('4', $profile->grade);
        $this->assertIsArray($profile->health_restrictions);
    }

    public function test_skips_inactive_or_unenrolled_students(): void
    {
        User::factory()->create([
            'user_type' => 'student',
            'student_status' => StudentStatus::PENDING,
        ]);

        $unenrolled = User::factory()->create([
            'user_type' => 'student',
            'student_status' => StudentStatus::ACTIVE,
        ]);

        Artisan::call('canteen:sync-all', ['--skip-staff' => true]);

        $this->assertSame(0, StudentProfile::query()->count());
        $this->assertNull(StudentProfile::query()->where('user_id', $unenrolled->id)->first());
    }

    public function test_updates_outdated_existing_profile(): void
    {
        $student = $this->makeActiveStudent('Old Name', '3', 'b');

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'student_id_ref' => (string) $student->id,
            'student_name' => 'Old Name',
            'grade' => '1',
            'class_name' => 'z',
            'is_active' => true,
        ]);

        $student->update(['name' => 'Updated Name']);
        $student->currentStudentEnrollment()->update(['grade_name' => '5', 'class_name' => 'c']);

        Artisan::call('canteen:sync-all', ['--skip-staff' => true]);

        $profile = StudentProfile::query()->where('user_id', $student->id)->first();
        $this->assertSame('Updated Name', $profile->student_name);
        $this->assertSame('5', $profile->grade);
        $this->assertSame('c', $profile->class_name);
    }

    public function test_registers_teachers_with_default_and_override_roles(): void
    {
        $lead = User::factory()->create(['user_type' => 'teacher', 'email' => 'lead@school.test']);
        $cashier = User::factory()->create(['user_type' => 'teacher', 'email' => 'pos@school.test']);

        Artisan::call('canteen:sync-all', [
            '--role' => 'cashier',
            '--staff' => ["{$lead->id}:manager"],
            '--skip-guardians' => true,
        ]);

        $this->assertSame('manager', Staff::query()->where('user_id', $lead->id)->value('role'));
        $this->assertSame('cashier', Staff::query()->where('user_id', $cashier->id)->value('role'));
    }

    public function test_manager_user_id_option_assigns_manager_and_persists_setting(): void
    {
        $manager = User::factory()->create(['user_type' => 'teacher', 'email' => 'mgr@school.test']);
        $cashier = User::factory()->create(['user_type' => 'teacher', 'email' => 'pos@school.test']);

        Artisan::call('canteen:sync-all', [
            '--manager-user-id' => (string) $manager->id,
            '--role' => 'cashier',
            '--skip-guardians' => true,
        ]);

        $this->assertSame('manager', Staff::query()->where('user_id', $manager->id)->value('role'));
        $this->assertSame('cashier', Staff::query()->where('user_id', $cashier->id)->value('role'));
        $stored = Setting::query()->where('key', CanteenSettingKeys::MANAGER_USER_ID)->first();
        $this->assertNotNull($stored);
        $this->assertSame($manager->id, (int) $stored->value);
    }

    public function test_manager_email_option_assigns_manager_role(): void
    {
        $manager = User::factory()->create(['user_type' => 'teacher', 'email' => 'lead@school.test']);
        User::factory()->create(['user_type' => 'teacher', 'email' => 'pos@school.test']);

        Artisan::call('canteen:sync-all', [
            '--manager-email' => 'lead@school.test',
            '--role' => 'cashier',
            '--skip-guardians' => true,
        ]);

        $this->assertSame('manager', Staff::query()->where('user_id', $manager->id)->value('role'));
    }

    public function test_rejects_manager_when_user_is_not_teacher(): void
    {
        $guardian = User::factory()->create(['user_type' => 'guardian', 'email' => 'parent@school.test']);

        $exitCode = Artisan::call('canteen:sync-all', [
            '--manager-user-id' => (string) $guardian->id,
            '--skip-guardians' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('not a teacher', Artisan::output());
    }

    public function test_reuses_persisted_manager_on_second_run_without_cli(): void
    {
        $manager = User::factory()->create(['user_type' => 'teacher']);
        User::factory()->create(['user_type' => 'teacher']);

        Artisan::call('canteen:sync-all', [
            '--manager-user-id' => (string) $manager->id,
            '--role' => 'cashier',
            '--skip-guardians' => true,
        ]);

        Staff::query()->where('user_id', $manager->id)->update(['role' => 'cashier']);

        Artisan::call('canteen:sync-all', [
            '--role' => 'cashier',
            '--skip-guardians' => true,
        ]);

        $this->assertSame('manager', Staff::query()->where('user_id', $manager->id)->value('role'));
    }

    public function test_falls_back_to_config_manager_user_id(): void
    {
        $manager = User::factory()->create(['user_type' => 'teacher']);
        User::factory()->create(['user_type' => 'teacher']);

        config(['canteen.teacher_staff.manager_user_id' => (string) $manager->id]);

        Artisan::call('canteen:sync-all', [
            '--role' => 'cashier',
            '--skip-guardians' => true,
        ]);

        $this->assertSame('manager', Staff::query()->where('user_id', $manager->id)->value('role'));
    }

    public function test_reports_students_missing_guardian_after_sync(): void
    {
        $student = $this->makeActiveStudent('Orphan Student', '2', 'a');

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'student_id_ref' => (string) $student->id,
            'student_name' => $student->name,
            'is_active' => true,
        ]);

        $exitCode = Artisan::call('canteen:sync-all', ['--skip-staff' => true]);

        $stats = app(CanteenSyncAllService::class)->run(['skip_staff' => true]);
        $this->assertCount(1, $stats['missing_guardians']);
        $this->assertSame($student->id, $stats['missing_guardians'][0]['user_id']);
        $this->assertTrue($stats['has_gaps']);
        $this->assertSame(1, $exitCode);
    }

    public function test_exit_code_zero_when_no_gaps(): void
    {
        $student = $this->makeActiveStudent('Complete Student', '4', 'a');
        $guardian = User::factory()->create(['user_type' => 'guardian']);
        $student->guardians()->attach($guardian->id, ['relationship_type' => 'father', 'is_primary' => true]);

        $exitCode = Artisan::call('canteen:sync-all', ['--skip-staff' => true]);

        $this->assertSame(0, $exitCode);
        $stats = app(CanteenSyncAllService::class)->run(['skip_staff' => true]);
        $this->assertFalse($stats['has_gaps']);
        $this->assertNotEmpty($stats['health_summaries']);
    }

    public function test_health_summaries_include_per_student_counters(): void
    {
        $student = $this->makeActiveStudent('Health Student', '3', 'b');

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'student_id_ref' => (string) $student->id,
            'student_name' => $student->name,
            'is_active' => true,
            'health_restrictions' => [
                'allergies' => ['peanut', 'gluten'],
                'blocked_tags' => ['dairy'],
                'block_all_purchases' => false,
                'notes' => '',
            ],
        ]);

        $stats = app(CanteenSyncAllService::class)->run(['skip_staff' => true, 'skip_guardians' => true]);
        $row = collect($stats['health_summaries'])->firstWhere('user_id', $student->id);

        $this->assertNotNull($row);
        $this->assertSame(2, $row['allergies_count']);
        $this->assertSame(1, $row['blocked_tags_count']);
        $this->assertFalse($row['block_all_purchases']);
    }

    public function test_syncs_guardian_links_for_active_students(): void
    {
        $student = $this->makeActiveStudent('Guarded Student', '3', 'b');
        $guardian = User::factory()->create(['user_type' => 'guardian']);
        $student->guardians()->attach($guardian->id, ['relationship_type' => 'father', 'is_primary' => true]);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'student_id_ref' => (string) $student->id,
            'student_name' => $student->name,
            'is_active' => true,
        ]);

        Artisan::call('canteen:sync-all', ['--skip-staff' => true]);

        $profile = StudentProfile::query()->where('user_id', $student->id)->first();
        $this->assertSame($guardian->id, $profile->primary_guardian_user_id);
    }

    public function test_is_idempotent_on_second_run(): void
    {
        $student = $this->makeActiveStudent('Repeat Student', '2', 'a');
        $service = app(CanteenSyncAllService::class);

        $first = $service->run(['skip_staff' => true]);
        $second = $service->run(['skip_staff' => true]);

        $this->assertSame(1, $first['profiles_created']);
        $this->assertSame(0, $second['profiles_created']);
        $this->assertSame(0, $second['profiles_updated']);
        $this->assertSame(1, StudentProfile::query()->where('user_id', $student->id)->count());
    }

    public function test_dry_run_does_not_persist_changes(): void
    {
        $this->makeActiveStudent('Dry Run Student', '1', 'a');

        Artisan::call('canteen:sync-all', ['--dry-run' => true, '--skip-staff' => true]);

        $this->assertSame(0, StudentProfile::query()->count());
    }

    protected function makeActiveStudent(string $name, string $grade, string $class): User
    {
        $student = User::factory()->create([
            'user_type' => 'student',
            'student_status' => StudentStatus::ACTIVE,
            'name' => $name,
        ]);

        $student->studentEnrollments()->create([
            'academic_year' => '2025-2026',
            'grade_name' => $grade,
            'class_name' => $class,
            'enrollment_date' => now()->toDateString(),
            'status' => 'active',
            'is_current' => true,
        ]);

        return $student;
    }

    protected function ensureSyncAllSchemaColumns(): void
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
