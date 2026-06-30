<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Student\StudentStatus;
use Illuminate\Console\Command;

class ShowCanteenReadyStudentsCommand extends Command
{
    protected $signature = 'students:canteen-ready {--limit=10 : Number of students to list}';

    protected $description = 'List students ready for canteen POS (active, enrolled, with wallet)';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $students = User::query()
            ->where('user_type', 'student')
            ->where('student_status', StudentStatus::ACTIVE)
            ->whereHas('currentStudentEnrollment')
            ->with(['wallet:id,user_id,balance', 'currentStudentEnrollment:id,student_id,grade_name,class_name,academic_year'])
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'name', 'student_code', 'email']);

        if ($students->isEmpty()) {
            $this->error('لا يوجد طلاب جاهزين للكافتيريا. شغّل: php artisan students:repair-for-canteen');

            return self::FAILURE;
        }

        $this->table(
            ['ID', 'Code', 'Name', 'Grade/Class', 'Wallet', 'Search in POS'],
            $students->map(fn (User $s) => [
                $s->id,
                $s->student_code ?? '—',
                $s->name,
                ($s->currentStudentEnrollment?->grade_name ?? '—').'/'.($s->currentStudentEnrollment?->class_name ?? '—'),
                $s->wallet ? number_format((float) $s->wallet->balance, 2) : 'no wallet',
                $s->student_code ?: (string) $s->id,
            ])->all(),
        );

        $this->line('استخدم student_code أو ID في شاشة الكافتيريا.');

        return self::SUCCESS;
    }
}
