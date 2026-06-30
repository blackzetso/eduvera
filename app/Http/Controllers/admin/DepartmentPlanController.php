<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DepartmentPlan;
use App\Models\Subject;
use App\Models\Timetable;
use App\Models\User;
use App\Services\DepartmentPlanService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DepartmentPlanController extends Controller
{
    public function __construct(
        private DepartmentPlanService $planService
    ) {}

    public function index(Request $request)
    {
        $timetable = Timetable::query()->where('status', 'active')->first()
            ?? Timetable::query()->first();

        if (! $timetable) {
            return Inertia::render('Admin/theme1/DepartmentPlan/Index', [
                'timetable' => null,
                'plan' => null,
                'planRows' => [],
                'workforceReport' => null,
                'departments' => [],
                'teachers' => [],
                'subjects' => [],
                'categories' => [],
                'executiveSummary' => [],
            ]);
        }

        $departmentLabel = $request->query('department')
            ?? $request->user()->department
            ?? 'قسم العلوم';

        $plan = $this->planService->getOrCreateForTimetable(
            $timetable,
            $request->user(),
            $departmentLabel
        );

        $plans = $this->planService->activePlansForTimetable($timetable);
        $planRows = $this->planService->buildPlanRows($plan);
        $workforceReport = $this->planService->workforceReport($plan);

        $categories = Category::with(['children' => fn ($q) => $q->with('children')])
            ->whereNull('parent_id')
            ->get();

        $teachers = User::query()
            ->where('user_type', 'teacher')
            ->with('subjects:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'department', 'job_title']);

        $subjects = Subject::orderBy('name')->get(['id', 'name']);

        $departments = $plans->pluck('department_label')->unique()->values();

        return Inertia::render('Admin/theme1/DepartmentPlan/Index', [
            'timetable' => $timetable->only(['id', 'name', 'academic_year']),
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'department_label' => $plan->department_label,
                'status' => $plan->status,
            ],
            'planRows' => $planRows,
            'staffing' => $plan->staffing->map(fn ($s) => [
                'id' => $s->id,
                'teacher_id' => $s->teacher_id,
                'teacher_name' => $s->teacher?->name,
                'subject_id' => $s->subject_id,
                'subject_name' => $s->subject?->name,
                'category_id' => $s->category_id,
                'allocated_periods' => $s->allocated_periods,
            ]),
            'workforceReport' => $workforceReport,
            'departments' => $departments,
            'teachers' => $teachers,
            'subjects' => $subjects,
            'categories' => $categories,
            'executiveSummary' => $this->planService->executiveSummary($plans),
            'selectedDepartment' => $departmentLabel,
        ]);
    }

    public function syncItems(Request $request, DepartmentPlan $plan)
    {
        $data = $request->validate([
            'items' => 'required|array',
            'items.*.subject_id' => 'required|exists:subjects,id',
            'items.*.category_id' => 'nullable|exists:categories,id',
            'items.*.required_periods' => 'required|integer|min:0|max:200',
        ]);

        $plan = $this->planService->syncItems($plan, $data['items']);

        return back()->with('success', 'تم حفظ متطلبات الخطة');
    }

    public function syncStaffing(Request $request, DepartmentPlan $plan)
    {
        $data = $request->validate([
            'staffing' => 'required|array',
            'staffing.*.teacher_id' => 'required|exists:users,id',
            'staffing.*.subject_id' => 'required|exists:subjects,id',
            'staffing.*.category_id' => 'nullable|exists:categories,id',
            'staffing.*.allocated_periods' => 'required|integer|min:0|max:80',
        ]);

        $plan = $this->planService->syncStaffing($plan, $data['staffing']);

        return back()->with('success', 'تم حفظ توزيع المعلمين');
    }

    public function activate(DepartmentPlan $plan)
    {
        DepartmentPlan::query()
            ->where('timetable_id', $plan->timetable_id)
            ->where('department_label', $plan->department_label)
            ->where('id', '!=', $plan->id)
            ->update(['status' => 'archived']);

        $plan->update(['status' => 'active']);
        $this->planService->syncPlanToTimetableSettings($plan->fresh(['items', 'staffing']));

        return back()->with('success', 'تم تفعيل خطة القسم');
    }
}
