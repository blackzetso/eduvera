<?php

namespace Tests\Feature\Canteen;

use App\Models\User;
use App\Modules\Canteen\Models\Staff;
use App\Modules\Canteen\Models\StudentProfile;
use App\Support\Student\StudentStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CanteenIntegrationCheckCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropAllTables();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('student_code')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->rememberToken();
            $table->string('profile_photo_path', 2048)->nullable();
            $table->foreignId('current_team_id')->nullable();
            $table->string('user_type')->default('student');
            $table->string('student_status', 32)->default('active');
            $table->timestamps();
        });

        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('academic_year', 16)->default('2025-2026');
            $table->string('grade_name')->nullable();
            $table->string('class_name')->nullable();
            $table->date('enrollment_date');
            $table->string('status', 32)->default('active');
            $table->boolean('is_current')->default(true);
            $table->timestamps();
        });

        Schema::create('canteen_student_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('primary_guardian_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('student_id_ref')->unique();
            $table->string('student_name');
            $table->json('health_restrictions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('canteen_staff', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('role');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        config(['canteen.enabled' => true]);
    }

    public function test_reports_missing_profile_and_unregistered_teacher(): void
    {
        $student = User::factory()->create([
            'user_type' => 'student',
            'student_status' => StudentStatus::ACTIVE,
        ]);
        $student->studentEnrollments()->create([
            'academic_year' => '2025-2026',
            'grade_name' => '3',
            'class_name' => 'b',
            'enrollment_date' => now()->toDateString(),
            'status' => 'active',
            'is_current' => true,
        ]);

        User::factory()->create(['user_type' => 'teacher', 'email' => 'missing@school.test']);

        $this->artisan('canteen:integration-check')
            ->expectsOutputToContain('Active enrolled missing profiles: 1')
            ->expectsOutputToContain('Teachers not registered: 1')
            ->assertExitCode(1);
    }

    public function test_passes_when_fully_integrated(): void
    {
        $guardian = User::factory()->create(['user_type' => 'guardian']);
        $student = User::factory()->create([
            'user_type' => 'student',
            'student_status' => StudentStatus::ACTIVE,
        ]);
        $student->studentEnrollments()->create([
            'academic_year' => '2025-2026',
            'grade_name' => '3',
            'class_name' => 'b',
            'enrollment_date' => now()->toDateString(),
            'status' => 'active',
            'is_current' => true,
        ]);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'student_id_ref' => (string) $student->id,
            'student_name' => $student->name,
            'primary_guardian_user_id' => $guardian->id,
            'health_restrictions' => ['allergies' => [], 'blocked_tags' => [], 'block_all_purchases' => false, 'notes' => ''],
            'is_active' => true,
        ]);

        $teacher = User::factory()->create(['user_type' => 'teacher']);
        Staff::query()->create(['user_id' => $teacher->id, 'role' => 'cashier', 'is_active' => true]);

        $this->artisan('canteen:integration-check')
            ->expectsOutputToContain('No integration gaps detected.')
            ->assertExitCode(0);
    }
}
