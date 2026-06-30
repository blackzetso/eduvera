<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Support\Student\AcademicYear;
use App\Models\User;
use App\Models\StudentEnrollment;

class SeedStudentEnrollments extends Command
{
    protected $signature = 'students:seed-enrollments';
    protected $description = 'Create current enrollments for all students if missing';

    public function handle()
    {
        $this->info('Starting student enrollment seeding...');

        $academicYear = AcademicYear::forDate();
        $gradeName    = '3';  // Adjust as needed
        $className    = 'b';  // Adjust as needed
        $enrollmentDate = now()->toDateString();

        $students = User::where('user_type', 'student')->get();

        $created = 0;
        $skipped = 0;

        foreach ($students as $student) {
            $hasCurrent = StudentEnrollment::where('student_id', $student->id)
                ->where('is_current', true)
                ->exists();

            if ($hasCurrent) {
                $skipped++;
                continue;
            }

            StudentEnrollment::create([
                'student_id'      => $student->id,
                'academic_year'   => $academicYear,
                'grade_name'      => $gradeName,
                'class_name'      => $className,
                'enrollment_date' => $enrollmentDate,
                'status'          => 'active',
                'action_type'     => 'initial',
                'is_current'      => true,
                'source'          => 'artisan_command',
            ]);

            $created++;
        }

        $this->info("تم الإنشاء: {$created} | تم التخطي (لديهم قيد نشط): {$skipped}");
    }
}