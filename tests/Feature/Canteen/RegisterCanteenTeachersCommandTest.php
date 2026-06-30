<?php

namespace Tests\Feature\Canteen;

use App\Models\User;
use App\Modules\Canteen\Models\Staff;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegisterCanteenTeachersCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropAllTables();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->rememberToken();
            $table->string('profile_photo_path', 2048)->nullable();
            $table->foreignId('current_team_id')->nullable();
            $table->string('user_type')->default('teacher');
            $table->timestamps();
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

    public function test_registers_all_teachers_with_default_role(): void
    {
        $teacher = User::factory()->create(['user_type' => 'teacher', 'email' => 't1@school.test']);
        User::factory()->create(['user_type' => 'student', 'email' => 's1@school.test']);

        Artisan::call('canteen:register-teachers', ['--role' => 'cashier']);

        $staff = Staff::query()->where('user_id', $teacher->id)->first();
        $this->assertNotNull($staff);
        $this->assertSame('cashier', $staff->role);
        $this->assertSame(1, Staff::query()->count());
    }

    public function test_applies_individual_role_mapping_and_updates_existing_row(): void
    {
        $manager = User::factory()->create(['user_type' => 'teacher', 'email' => 'lead@school.test']);
        $cashier = User::factory()->create(['user_type' => 'teacher', 'email' => 'pos@school.test']);

        Staff::query()->create([
            'user_id' => $manager->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        Artisan::call('canteen:register-teachers', [
            '--role' => 'cashier',
            '--staff' => ["{$manager->id}:manager"],
        ]);

        $this->assertSame('manager', Staff::query()->where('user_id', $manager->id)->value('role'));
        $this->assertSame('cashier', Staff::query()->where('user_id', $cashier->id)->value('role'));
    }

    public function test_is_idempotent_on_second_run(): void
    {
        User::factory()->create(['user_type' => 'teacher']);

        Artisan::call('canteen:register-teachers');
        Artisan::call('canteen:register-teachers');

        $this->assertSame(1, Staff::query()->count());
    }

    public function test_dry_run_does_not_create_rows(): void
    {
        User::factory()->create(['user_type' => 'teacher']);

        Artisan::call('canteen:register-teachers', ['--dry-run' => true]);

        $this->assertSame(0, Staff::query()->count());
    }
}
