<?php

namespace App\Http\Controllers\web;

use App\Http\Controllers\Controller;
use App\Models\GuardianNotificationPreference;
use App\Models\LessonEnrollment;
use App\Models\StudentBehaviorRecord;
use App\Models\StudentGrade;
use App\Models\User;
use App\Models\UserWallet;
use App\Services\AttendanceStatsService;
use App\Services\GuardianPortalService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GuardianPortalController extends Controller
{
    public function dashboard(Request $request, GuardianPortalService $portal)
    {
        $guardian = $request->user();
        $children = $portal->childrenForGuardian($guardian);

        $summaries = [];
        foreach ($children as $child) {
            $summaries[$child->id] = $portal->childCardSummary($child);
        }

        return Inertia::render('Guardian/theme1/Dashboard/Index', [
            'guardian' => $guardian->only(['id', 'name', 'email', 'phone', 'national_id']),
            'children' => $children,
            'summaries' => $summaries,
        ]);
    }

    public function childOverview(Request $request, User $student, GuardianPortalService $portal)
    {
        $guardian = $request->user();
        $portal->assertGuardianCanAccessChild($guardian, $student);
        $student->load('category');

        return Inertia::render('Guardian/theme1/Students/Overview', $this->childPageProps($guardian, $student, $portal));
    }

    public function childAttendance(Request $request, User $student, GuardianPortalService $portal, AttendanceStatsService $stats)
    {
        $guardian = $request->user();
        $portal->assertGuardianCanAccessChild($guardian, $student);

        return Inertia::render('Guardian/theme1/Students/Attendance', array_merge(
            $this->childPageProps($guardian, $student, $portal),
            ['summary' => $stats->studentSummary($student->id)]
        ));
    }

    public function childGrades(Request $request, User $student, GuardianPortalService $portal)
    {
        $guardian = $request->user();
        $portal->assertGuardianCanAccessChild($guardian, $student);

        $grades = StudentGrade::query()
            ->where('student_id', $student->id)
            ->with('subject:id,name')
            ->orderByDesc('assessed_at')
            ->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'title' => $g->title,
                'subject' => $g->subject?->name,
                'assessment_type' => $g->assessment_type,
                'term_label' => $g->term_label,
                'score' => $g->score,
                'max_score' => $g->max_score,
                'percentage' => $g->percentage(),
                'assessed_at' => $g->assessed_at?->toDateString(),
            ]);

        return Inertia::render('Guardian/theme1/Students/Grades', array_merge(
            $this->childPageProps($guardian, $student, $portal),
            ['grades' => $grades]
        ));
    }

    public function childBehavior(Request $request, User $student, GuardianPortalService $portal)
    {
        $guardian = $request->user();
        $portal->assertGuardianCanAccessChild($guardian, $student);

        $records = StudentBehaviorRecord::query()
            ->where('student_id', $student->id)
            ->orderByDesc('occurred_at')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'severity' => $r->severity,
                'category' => $r->category,
                'title' => $r->title,
                'description' => $r->description,
                'occurred_at' => $r->occurred_at?->toDateString(),
            ]);

        return Inertia::render('Guardian/theme1/Students/Behavior', array_merge(
            $this->childPageProps($guardian, $student, $portal),
            ['records' => $records]
        ));
    }

    public function childSchedule(Request $request, User $student, GuardianPortalService $portal)
    {
        $guardian = $request->user();
        $portal->assertGuardianCanAccessChild($guardian, $student);

        return Inertia::render('Guardian/theme1/Students/Schedule', array_merge(
            $this->childPageProps($guardian, $student, $portal),
            ['schedule' => $portal->childSchedule($student)->values()]
        ));
    }

    public function notificationSettings(Request $request, GuardianPortalService $portal)
    {
        $guardian = $request->user();
        $children = $portal->childrenForGuardian($guardian);

        $preferences = GuardianNotificationPreference::query()
            ->where('guardian_id', $guardian->id)
            ->get()
            ->keyBy('student_id');

        return Inertia::render('Guardian/theme1/Settings/Notifications', [
            'guardian' => $guardian->only(['id', 'name', 'email']),
            'children' => $children,
            'preferences' => $preferences,
        ]);
    }

    public function walletTransfer(Request $request, GuardianPortalService $portal)
    {
        $validated = $request->validate([
            'to_student_id' => ['required', 'integer'],
            'amount'        => ['required', 'numeric', 'min:1'],
            'description'   => ['nullable', 'string', 'max:200'],
        ]);

        $guardian = $request->user();
        $student  = User::findOrFail($validated['to_student_id']);
        $portal->assertGuardianCanAccessChild($guardian, $student);

        $gWallet     = UserWallet::firstOrCreate(
            ['user_id' => $guardian->id],
            ['balance' => 0, 'total_credited' => 0, 'total_debited' => 0]
        );
        $childWallet = UserWallet::firstOrCreate(
            ['user_id' => $student->id],
            ['balance' => 0, 'total_credited' => 0, 'total_debited' => 0]
        );

        if (! $gWallet->hasBalance($validated['amount'])) {
            return back()->withErrors(['amount' => 'الرصيد غير كافٍ لإتمام التحويل.']);
        }

        $desc = $validated['description'] ?? "تحويل مصروف لـ {$student->name}";
        $gWallet->transferTo($childWallet, (float) $validated['amount'], $desc);

        return back()->with('success', 'تم التحويل بنجاح.');
    }

    public function childCourses(Request $request, User $student, GuardianPortalService $portal)
    {
        $guardian = $request->user();
        $portal->assertGuardianCanAccessChild($guardian, $student);

        $enrollments = LessonEnrollment::query()
            ->where('student_id', $student->id)
            ->with(['lesson.lectures', 'lesson.category', 'lectureViews'])
            ->orderByDesc('enrolled_at')
            ->get()
            ->map(function (LessonEnrollment $e) {
                $totalLectures  = $e->lesson?->lectures->count() ?? 0;
                $viewedLectures = $e->lectureViews->count();

                return [
                    'id'              => $e->id,
                    'status'          => $e->status,
                    'enrolled_at'     => $e->enrolled_at?->toDateString(),
                    'expires_at'      => $e->expires_at?->toDateString(),
                    'progress'        => $e->progressPercent(),
                    'total_lectures'  => $totalLectures,
                    'viewed_lectures' => $viewedLectures,
                    'lesson'          => [
                        'id'    => $e->lesson?->id,
                        'name'  => $e->lesson?->name,
                        'image' => $e->lesson?->image,
                        'category' => $e->lesson?->category?->name,
                    ],
                ];
            });

        return Inertia::render('Guardian/theme1/Students/Courses', array_merge(
            $this->childPageProps($guardian, $student, $portal),
            ['enrollments' => $enrollments]
        ));
    }

    public function wallet(Request $request, GuardianPortalService $portal)
    {
        $guardian = $request->user();
        $children = $portal->childrenForGuardian($guardian);

        $guardianWallet = UserWallet::firstOrCreate(
            ['user_id' => $guardian->id],
            ['balance' => 0, 'total_credited' => 0, 'total_debited' => 0]
        );

        $guardianWallet->load(['transactions' => fn ($q) => $q->orderByDesc('created_at')->limit(30)]);

        return Inertia::render('Guardian/theme1/Wallet/Index', [
            'guardian'       => $guardian->only(['id', 'name', 'email']),
            'children'       => $children,
            'guardianWallet' => $guardianWallet,
        ]);
    }

    public function childWallet(Request $request, User $student, GuardianPortalService $portal)
    {
        $guardian = $request->user();
        $portal->assertGuardianCanAccessChild($guardian, $student);

        $wallet = UserWallet::firstOrCreate(
            ['user_id' => $student->id],
            ['balance' => 0, 'total_credited' => 0, 'total_debited' => 0]
        );

        $wallet->load(['transactions' => fn ($q) => $q->orderByDesc('created_at')->limit(50)]);

        return Inertia::render('Guardian/theme1/Students/Wallet', array_merge(
            $this->childPageProps($guardian, $student, $portal),
            ['wallet' => $wallet]
        ));
    }

    protected function childPageProps(User $guardian, User $student, GuardianPortalService $portal): array
    {
        $student->loadMissing('category');

        return [
            'guardian' => $guardian->only(['id', 'name', 'email']),
            'children' => $portal->childrenForGuardian($guardian),
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'student_code' => $student->student_code,
                'category' => $student->category?->only(['id', 'name']),
            ],
            'summary' => $portal->childCardSummary($student),
        ];
    }
}
