<?php

namespace App\Http\Controllers\teacher;

use App\Models\Category;
use App\Models\Timetable;
use App\Models\TimetableAssignment;
use App\Models\TimetablePeriod;
use App\Models\User;
use App\Services\TimetableRoleAssignmentService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Inertia\Inertia;

class TimetableController extends Controller
{
    public function __construct(
        private TimetableRoleAssignmentService $assignmentService
    ) {}

    /**
     * Display the teacher's timetable.
     */
    public function index(Request $request)
    {
        $teacher = $request->user();
        $teacherId = $teacher->id;

        $timetable = Timetable::where('status', 'active')
            ->with([
                'days.periods.category',
                'days.periods.assignments' => function ($query) use ($teacherId) {
                    $query->where('teacher_id', $teacherId);
                },
                'days.periods.assignments.subject',
                'periods.category',
                'periods.assignments' => function ($query) use ($teacherId) {
                    $query->where('teacher_id', $teacherId);
                },
                'periods.assignments.subject',
            ])
            ->first();

        if (! $timetable) {
            return Inertia::render('Teacher/Timetable/Index', [
                'timetable' => null,
                'myAssignments' => [],
                'availablePeriods' => [],
                'subjects' => [],
            ]);
        }

        $myAssignments = TimetableAssignment::where('teacher_id', $teacherId)
            ->with(['period.timetable', 'period.day', 'period.category.parent', 'subject'])
            ->get();

        $context = $this->assignmentService->buildTeacherContext(
            $teacher,
            Category::with(['children' => fn ($q) => $q->with('children')])->whereNull('parent_id')->get()
        );

        $visibleIds = $context['visible_category_ids'] ?? [];

        $availablePeriods = TimetablePeriod::where('timetable_id', $timetable->id)
            ->with(['timetable', 'day', 'category.parent', 'assignments.teacher', 'assignments.subject'])
            ->get()
            ->filter(function ($period) use ($teacherId, $visibleIds) {
                if ($visibleIds && $period->category_id && ! in_array((int) $period->category_id, $visibleIds, true)) {
                    return false;
                }

                return $period->assignments->isEmpty()
                    || $period->assignments->where('teacher_id', $teacherId)->isNotEmpty();
            })->values();

        $subjects = $teacher->teachingSubjects();

        return Inertia::render('Teacher/Timetable/Index', [
            'timetable' => $timetable,
            'myAssignments' => $myAssignments,
            'availablePeriods' => $availablePeriods,
            'subjects' => $subjects,
        ]);
    }

    /**
     * Grid view for teacher self-assignment (تعيين الحصص).
     */
    public function grid(Request $request)
    {
        $teacher = $request->user();
        $payload = $this->loadTimetableGridPayload($teacher);

        return Inertia::render('Teacher/Timetable/Assign', array_merge($payload, [
            'wizardMode' => 'teacher_assign',
            'initialStep' => 6,
        ]));
    }

    /**
     * @return array{timetable: ?Timetable, categories: \Illuminate\Support\Collection, subjects: \Illuminate\Support\Collection, assignmentContext: array, teachers: array}
     */
    private function loadTimetableGridPayload(User $teacher): array
    {
        $categories = Category::with(['children' => fn ($q) => $q->with('children')])
            ->whereNull('parent_id')
            ->get();

        $assignmentContext = $this->assignmentService->buildTeacherContext($teacher, $categories);
        $subjects = $teacher->teachingSubjects();

        $timetable = Timetable::where('status', 'active')
            ->with([
                'days.periods.category.parent',
                'days.periods.assignments.teacher',
                'days.periods.assignments.subject',
                'periods.category.parent',
                'periods.assignments.teacher',
                'periods.assignments.subject',
            ])
            ->first();

        return [
            'timetable' => $timetable,
            'categories' => $categories,
            'subjects' => $subjects,
            'assignmentContext' => $assignmentContext,
            'teachers' => $assignmentContext['teachers'] ?? [],
        ];
    }

    /**
     * Teacher assigns themselves to a period.
     */
    public function assignSelf(Request $request)
    {
        $teacher = $request->user();
        $teacherId = $teacher->id;

        $data = $request->validate([
            'timetable_period_id' => 'required|exists:timetable_periods,id',
            'subject_id' => [
                'required',
                'exists:subjects,id',
                function ($attribute, $value, $fail) use ($teacherId) {
                    if (! $this->assignmentService->teacherTeachesSubject(
                        User::find($teacherId),
                        (int) $value
                    )) {
                        $fail('أنت غير مسجل لتدريس هذه المادة.');
                    }
                },
            ],
        ]);

        $period = TimetablePeriod::with(['timetable', 'day', 'category', 'assignments'])->findOrFail($data['timetable_period_id']);

        if (! $this->assignmentService->canTeacherSelfAssignPeriod($teacher, $period)) {
            return back()->withErrors([
                'timetable_period_id' => 'لا يمكنك التعيين على هذه الحصة (مرحلة أو تعيين سابق).',
            ]);
        }

        $existing = TimetableAssignment::where('timetable_period_id', $data['timetable_period_id'])
            ->where('teacher_id', $teacherId)
            ->first();

        if ($existing) {
            return back()->withErrors([
                'timetable_period_id' => 'أنت معين بالفعل على هذه الحصة',
            ]);
        }

        $periodAssigned = TimetableAssignment::where('timetable_period_id', $data['timetable_period_id'])
            ->where('teacher_id', '!=', $teacherId)
            ->where('type', 'main')
            ->first();

        if ($periodAssigned) {
            return back()->withErrors([
                'timetable_period_id' => 'هذه الحصة معينة لمدرس آخر',
            ]);
        }

        $conflict = TimetableAssignment::whereHas('period', function ($query) use ($period, $teacherId) {
            $query->where('timetable_id', $period->timetable_id)
                ->where('timetable_day_id', $period->timetable_day_id)
                ->where('teacher_id', $teacherId)
                ->where(function ($q) use ($period) {
                    $q->where('time_from', '<', $period->time_to)
                        ->where('time_to', '>', $period->time_from);
                });
        })->first();

        if ($conflict) {
            return back()->withErrors([
                'timetable_period_id' => 'لديك حصة أخرى في نفس الوقت',
            ]);
        }

        TimetableAssignment::create([
            'timetable_period_id' => $data['timetable_period_id'],
            'teacher_id' => $teacherId,
            'subject_id' => $data['subject_id'],
            'type' => 'main',
            'assigned_by' => $teacherId,
            'status' => 'approved',
        ]);

        return back()->with('success', 'تم إضافة نفسك للحصة بنجاح');
    }
}
