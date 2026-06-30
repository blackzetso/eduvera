<?php

namespace Database\Seeders;

use App\Models\Lesson;
use App\Models\LessonEnrollment;
use App\Models\LectureView;
use App\Models\StudentBehaviorRecord;
use App\Models\StudentGrade;
use App\Models\Subject;
use App\Models\User;
use App\Models\UserWallet;
use Illuminate\Database\Seeder;

class GuardianPortalSeeder extends Seeder
{
    public function run(): void
    {
        $subjects      = Subject::all();
        $socialWorker  = User::query()->where('user_type', 'social_worker')->first();
        $students      = User::query()->ofType('student')->with('category')->get();
        $guardians     = User::query()->ofType('guardian')->get();
        $lessons       = Lesson::with('lectures')->get();

        $this->seedGrades($students, $subjects);
        $this->seedBehaviorRecords($students, $socialWorker);
        $this->seedWallets($guardians, $students);
        $this->seedEnrollments($students, $lessons);
    }

    // ------------------------------------------------------------------ grades
    private function seedGrades($students, $subjects): void
    {
        $gradeTitles = [
            'exam'       => ['امتحان نصف العام', 'امتحان آخر العام', 'اختبار شهري'],
            'quiz'       => ['اختبار قصير', 'مذاكرة سريعة'],
            'assignment' => ['واجب منزلي', 'مشروع صفي'],
        ];

        foreach ($students as $student) {
            if ($subjects->isEmpty()) {
                continue;
            }

            foreach ($subjects->random(min(4, $subjects->count())) as $subject) {
                foreach (['exam', 'quiz', 'assignment'] as $type) {
                    $title = $gradeTitles[$type][array_rand($gradeTitles[$type])];
                    $max   = $type === 'exam' ? 100 : 50;
                    $score = rand((int) ($max * 0.55), (int) ($max * 0.98));

                    StudentGrade::create([
                        'student_id'      => $student->id,
                        'subject_id'      => $subject->id,
                        'term_label'      => '2025-2026 - الفصل الأول',
                        'assessment_type' => $type,
                        'title'           => $title,
                        'score'           => $score,
                        'max_score'       => $max,
                        'assessed_at'     => now()->subDays(rand(5, 60))->toDateString(),
                    ]);
                }
            }
        }
    }

    // -------------------------------------------------------- behavior records
    private function seedBehaviorRecords($students, $socialWorker): void
    {
        $behaviorSamples = [
            ['severity' => 'positive', 'category' => 'مشاركة',  'title' => 'مشاركة فعالة في الحصة'],
            ['severity' => 'positive', 'category' => 'انضباط',  'title' => 'التزام بقواعد الفصل'],
            ['severity' => 'neutral',  'category' => 'عام',      'title' => 'ملاحظة من المعلم'],
            ['severity' => 'negative', 'category' => 'تأخير',   'title' => 'تأخر متكرر عن الحصة'],
            ['severity' => 'negative', 'category' => 'سلوك',    'title' => 'إزعاج الزملاء'],
        ];

        foreach ($students as $index => $student) {
            $behaviorCount = 2 + ($index % 4);
            for ($b = 0; $b < $behaviorCount; $b++) {
                $sample = $behaviorSamples[($index + $b) % count($behaviorSamples)];
                StudentBehaviorRecord::create([
                    'student_id'  => $student->id,
                    'severity'    => $sample['severity'],
                    'category'    => $sample['category'],
                    'title'       => $sample['title'],
                    'description' => 'ملاحظة مسجلة من إدارة المدرسة للمتابعة مع ولي الأمر.',
                    'occurred_at' => now()->subDays(rand(3, 45))->toDateString(),
                    'recorded_by' => $socialWorker?->id,
                ]);
            }
        }
    }

    // --------------------------------------------------------------- wallets
    private function seedWallets($guardians, $students): void
    {
        // Create wallets for all guardians
        foreach ($guardians as $guardian) {
            $balance = rand(200, 1000);
            /** @var UserWallet $gWallet */
            $gWallet = UserWallet::firstOrCreate(
                ['user_id' => $guardian->id],
                [
                    'balance'        => 0,
                    'total_credited' => 0,
                    'total_debited'  => 0,
                ]
            );

            // Initial recharge
            $gWallet->credit($balance, 'شحن رصيد أولي');
        }

        // Create wallets for all students and seed a few transfers from guardian
        foreach ($students as $student) {
            UserWallet::firstOrCreate(
                ['user_id' => $student->id],
                [
                    'balance'        => 0,
                    'total_credited' => 0,
                    'total_debited'  => 0,
                ]
            );
        }

        // For each guardian → transfer small amounts to their children
        foreach ($guardians as $guardian) {
            $gWallet   = $guardian->wallet()->first();
            $children  = $guardian->students()->get();

            if (! $gWallet || $children->isEmpty()) {
                continue;
            }

            foreach ($children as $child) {
                $childWallet = $child->wallet()->first();
                if (! $childWallet) {
                    continue;
                }

                // 1–3 transfers, 20–100 EGP each
                $transfers = rand(1, 3);
                for ($t = 0; $t < $transfers; $t++) {
                    $amount = rand(20, 100);
                    if (! $gWallet->hasBalance($amount)) {
                        break;
                    }
                    $gWallet->transferTo($childWallet, $amount, "تحويل مصروف لـ {$child->name}");
                    // refresh after each debit
                    $gWallet->refresh();
                }
            }
        }
    }

    // ----------------------------------------------------------- enrollments
    private function seedEnrollments($students, $lessons): void
    {
        if ($lessons->isEmpty()) {
            return;
        }

        foreach ($students as $student) {
            // Each student enrolls in 1–3 random lessons
            $pick = $lessons->random(min(rand(1, 3), $lessons->count()));

            foreach ($pick as $lesson) {
                $enrollment = LessonEnrollment::firstOrCreate(
                    ['student_id' => $student->id, 'lesson_id' => $lesson->id],
                    [
                        'status'      => 'active',
                        'enrolled_at' => now()->subDays(rand(10, 60)),
                    ]
                );

                // Mark a random subset of lectures as viewed
                $lectures = $lesson->lectures;
                if ($lectures->isEmpty()) {
                    continue;
                }

                $viewCount = rand(0, $lectures->count());
                foreach ($lectures->take($viewCount) as $lecture) {
                    LectureView::firstOrCreate(
                        ['enrollment_id' => $enrollment->id, 'lecture_id' => $lecture->id],
                        [
                            'first_viewed_at' => now()->subDays(rand(1, 30)),
                            'last_viewed_at'  => now()->subDays(rand(0, 5)),
                        ]
                    );
                }
            }
        }
    }
}
