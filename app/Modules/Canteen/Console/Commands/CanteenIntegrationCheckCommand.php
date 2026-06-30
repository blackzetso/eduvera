<?php

namespace App\Modules\Canteen\Console\Commands;

use App\Models\User;
use App\Modules\Canteen\Models\Staff;
use App\Modules\Canteen\Models\StudentProfile;
use App\Support\Student\StudentStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CanteenIntegrationCheckCommand extends Command
{
    protected $signature = 'canteen:integration-check';

    protected $description = 'Check Canteen integration with students, teachers, and guardians';

    public function handle(): int
    {
        $this->info('--- Canteen Integration Check ---');
        $this->line('Module: '.(config('canteen.enabled') ? 'ENABLED' : 'DISABLED'));
        $this->line('Adapters: student='.config('canteen.integration.student_adapter')
            .', guardian='.config('canteen.integration.guardian_adapter')
            .', wallet='.config('canteen.integration.wallet_adapter'));
        $this->newLine();

        $students = User::query()->where('user_type', 'student')->get(['id', 'name', 'student_code', 'student_status']);
        $profileUserIds = StudentProfile::query()->whereNotNull('user_id')->pluck('user_id')->all();
        $profileRefs = StudentProfile::query()->pluck('student_id_ref')->all();

        $studentsWithoutProfiles = $students->filter(function (User $student) use ($profileUserIds, $profileRefs) {
            return ! in_array($student->id, $profileUserIds, true)
                && ! in_array((string) $student->id, $profileRefs, true);
        });

        $activeEnrolled = User::query()
            ->where('user_type', 'student')
            ->where('student_status', StudentStatus::ACTIVE)
            ->whereHas('currentStudentEnrollment')
            ->count();

        $activeMissingProfile = User::query()
            ->where('user_type', 'student')
            ->where('student_status', StudentStatus::ACTIVE)
            ->whereHas('currentStudentEnrollment')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('canteen_student_profiles as p')
                    ->whereNull('p.deleted_at')
                    ->where(function ($inner) {
                        $inner->whereColumn('p.user_id', 'users.id')
                            ->orWhereColumn('p.student_id_ref', DB::raw('CAST(users.id AS CHAR)'));
                    });
            })
            ->count();

        $this->comment('Students');
        $this->line("Total students: {$students->count()}");
        $this->line('Active enrolled (POS-eligible): '.$activeEnrolled);
        $this->line('Canteen profiles: '.StudentProfile::query()->count());
        $this->line("Students missing Canteen profiles (all): {$studentsWithoutProfiles->count()}");
        $this->line("Active enrolled missing profiles: {$activeMissingProfile}");

        if ($studentsWithoutProfiles->isNotEmpty()) {
            $this->table(
                ['ID', 'Name', 'Code', 'Status'],
                $studentsWithoutProfiles->take(10)->map(fn (User $s) => [
                    $s->id,
                    $s->name,
                    $s->student_code ?? '—',
                    $s->student_status ?? '—',
                ])->all(),
            );
        }

        $this->newLine();
        $this->comment('Teachers / Staff');

        $teachers = User::query()->where('user_type', 'teacher')->get(['id', 'name', 'email']);
        $canteenStaffUserIds = Staff::query()->where('is_active', true)->pluck('user_id')->all();
        $unregisteredTeachers = $teachers->filter(
            fn (User $teacher) => ! in_array($teacher->id, $canteenStaffUserIds, true)
        );

        $this->line("Total teachers: {$teachers->count()}");
        $this->line('Teachers registered in Canteen (active): '.count($canteenStaffUserIds));
        $this->line("Teachers not registered: {$unregisteredTeachers->count()}");

        if ($unregisteredTeachers->isNotEmpty()) {
            $this->table(
                ['ID', 'Name', 'Email'],
                $unregisteredTeachers->take(10)->map(fn (User $t) => [$t->id, $t->name, $t->email])->all(),
            );
        }

        $this->newLine();
        $this->comment('Guardians');

        $guardianMissing = StudentProfile::query()->whereNull('primary_guardian_user_id')->count();
        $this->line("Students missing primary guardian link: {$guardianMissing}");

        if ($guardianMissing > 0) {
            $samples = StudentProfile::query()
                ->whereNull('primary_guardian_user_id')
                ->whereNotNull('user_id')
                ->with('user:id,name,student_code')
                ->limit(10)
                ->get();

            $this->table(
                ['User ID', 'Name', 'Student code'],
                $samples->map(fn (StudentProfile $p) => [
                    $p->user_id,
                    $p->user?->name ?? $p->student_name,
                    $p->user?->student_code ?? '—',
                ])->all(),
            );
        }

        $this->newLine();
        $this->comment('Health / Restrictions');

        $studentsWithoutHealth = StudentProfile::query()->whereNull('health_restrictions')->count();
        $profilesNeedingBootstrap = StudentProfile::query()
            ->whereNotNull('user_id')
            ->get()
            ->filter(fn (StudentProfile $p) => $p->health_restrictions === null || $p->health_restrictions === [])
            ->count();

        $this->line("Profiles with NULL health_restrictions: {$studentsWithoutHealth}");
        $this->line("Profiles needing health defaults: {$profilesNeedingBootstrap}");

        $this->newLine();
        $this->info('--- Check Complete ---');

        $hasIssues = $studentsWithoutProfiles->isNotEmpty()
            || $activeMissingProfile > 0
            || $unregisteredTeachers->isNotEmpty()
            || $guardianMissing > 0
            || $profilesNeedingBootstrap > 0;

        if ($hasIssues) {
            $this->warn('Issues found. Suggested: php artisan canteen:sync-all --dry-run');

            return self::FAILURE;
        }

        $this->info('No integration gaps detected.');

        return self::SUCCESS;
    }
}
