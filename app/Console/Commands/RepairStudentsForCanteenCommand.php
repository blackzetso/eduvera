<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserWallet;
use App\Modules\Canteen\Services\CanteenStudentProfileSyncService;
use App\Services\StudentCodeService;
use App\Services\StudentEnrollmentService;
use App\Support\Student\AcademicYear;
use App\Support\Student\StudentStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairStudentsForCanteenCommand extends Command
{
    protected $signature = 'students:repair-for-canteen
                            {--wallet-balance=100 : Initial wallet balance for students without a wallet}
                            {--grade=3 : Default grade_name when seeding enrollment without category}
                            {--class=b : Default class_name when seeding enrollment without category}
                            {--dry-run : Show actions without writing}';

    protected $description = 'Backfill missing enrollments, student codes, active status, wallets, and canteen profiles';

    public function handle(
        StudentEnrollmentService $enrollments,
        StudentCodeService $studentCodes,
        CanteenStudentProfileSyncService $canteenSync,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $walletBalance = (float) $this->option('wallet-balance');
        $gradeName = (string) $this->option('grade');
        $className = (string) $this->option('class');
        $academicYear = AcademicYear::forDate();

        $stats = [
            'enrollments_created' => 0,
            'status_fixed' => 0,
            'codes_assigned' => 0,
            'wallets_created' => 0,
            'canteen_profiles_synced' => 0,
            'skipped' => 0,
        ];

        $students = User::query()
            ->where('user_type', 'student')
            ->orderBy('id')
            ->get();

        $this->info('Students found: '.$students->count());

        foreach ($students as $student) {
            $changed = false;

            if (! $student->student_code) {
                if (! $dryRun) {
                    $studentCodes->assignIfMissing($student);
                    $student->refresh();
                }
                $stats['codes_assigned']++;
                $changed = true;
            }

            if ($student->student_status !== StudentStatus::ACTIVE) {
                if (! $dryRun) {
                    $student->update(['student_status' => StudentStatus::ACTIVE]);
                }
                $stats['status_fixed']++;
                $changed = true;
            }

            $hasCurrent = $student->studentEnrollments()
                ->where('is_current', true)
                ->exists();

            if (! $hasCurrent) {
                if ($student->category_id) {
                    if (! $dryRun) {
                        $enrollments->recordInitialEnrollment(
                            $student,
                            (int) $student->category_id,
                            $student->enrollment_date?->toDateString(),
                            'initial',
                            'repair_command',
                        );
                    }
                } else {
                    if (! $dryRun) {
                        DB::table('student_enrollments')->insert([
                            'student_id' => $student->id,
                            'academic_year' => $academicYear,
                            'grade_name' => $gradeName,
                            'class_name' => $className,
                            'enrollment_date' => now()->toDateString(),
                            'status' => 'active',
                            'action_type' => 'initial',
                            'is_current' => true,
                            'source' => 'repair_command',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
                $stats['enrollments_created']++;
                $changed = true;
            }

            if (! $student->wallet) {
                if (! $dryRun) {
                    UserWallet::query()->create([
                        'user_id' => $student->id,
                        'balance' => $walletBalance,
                        'total_credited' => $walletBalance,
                        'total_debited' => 0,
                    ]);
                }
                $stats['wallets_created']++;
                $changed = true;
            }

            if (! $dryRun && config('canteen.enabled', false)) {
                $canteenSync->syncFromUser($student->fresh());
                $stats['canteen_profiles_synced']++;
                $changed = true;
            }

            if (! $changed) {
                $stats['skipped']++;
            }
        }

        $this->table(
            ['Metric', 'Count'],
            collect($stats)->map(fn ($count, $key) => [$key, $count])->values()->all(),
        );

        if ($dryRun) {
            $this->warn('Dry run only — no changes were saved.');
        } else {
            $this->info('Student repair completed.');
        }

        return self::SUCCESS;
    }
}
