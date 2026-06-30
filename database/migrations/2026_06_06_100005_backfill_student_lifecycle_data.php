<?php

use App\Models\User;
use App\Services\StudentEnrollmentService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('user_type', 'student')
            ->whereNull('student_status')
            ->update(['student_status' => 'active']);

        /** @var StudentEnrollmentService $enrollments */
        $enrollments = app(StudentEnrollmentService::class);

        User::query()
            ->where('user_type', 'student')
            ->orderBy('id')
            ->chunkById(100, function ($students) use ($enrollments) {
                foreach ($students as $student) {
                    $enrollments->backfillForStudent($student);
                }
            });

        $this->ensurePrimaryGuardians();
    }

    public function down(): void
    {
        // Data backfill is not reversed.
    }

    protected function ensurePrimaryGuardians(): void
    {
        $studentIds = DB::table('guardian_student')
            ->select('student_id')
            ->groupBy('student_id')
            ->pluck('student_id');

        foreach ($studentIds as $studentId) {
            $hasPrimary = DB::table('guardian_student')
                ->where('student_id', $studentId)
                ->where('is_primary', true)
                ->exists();

            if ($hasPrimary) {
                continue;
            }

            $firstGuardianId = DB::table('guardian_student')
                ->where('student_id', $studentId)
                ->orderBy('id')
                ->value('guardian_id');

            if ($firstGuardianId) {
                DB::table('guardian_student')
                    ->where('student_id', $studentId)
                    ->where('guardian_id', $firstGuardianId)
                    ->update(['is_primary' => true]);
            }
        }
    }
};
