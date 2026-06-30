<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StudentLifecycleService;
use App\Support\Student\GuardianRelationship;
use Illuminate\Http\Request;

class StudentLifecycleController extends Controller
{
    public function __construct(
        protected StudentLifecycleService $lifecycle,
    ) {}

    public function promote(Request $request, User $student)
    {
        $this->authorize('lifecycle.promote', $student);
        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'academic_year' => 'required|string|max:16',
            'enrollment_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        $this->lifecycle->promote($student, $data);

        return redirect()
            ->to(route('admin.students.show', $student) . '?tab=enrollment')
            ->with('success', 'تمت ترقية الطالب بنجاح');
    }

    public function transfer(Request $request, User $student)
    {
        $this->authorize('lifecycle.transfer', $student);
        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'transfer_date' => 'required|date',
            'reason' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:2000',
        ]);

        $this->lifecycle->transfer($student, $data);

        return redirect()
            ->to(route('admin.students.show', $student) . '?tab=enrollment')
            ->with('success', 'تم نقل الطالب بنجاح');
    }

    public function withdraw(Request $request, User $student)
    {
        $this->authorize('lifecycle.withdraw', $student);
        $data = $request->validate([
            'withdrawal_date' => 'required|date',
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:2000',
        ]);

        $this->lifecycle->withdraw($student, $data);

        return redirect()
            ->to(route('admin.students.show', $student) . '?tab=enrollment')
            ->with('success', 'تم تسجيل انسحاب الطالب بنجاح');
    }

    public function reEnroll(Request $request, User $student)
    {
        $this->authorize('lifecycle.reEnroll', $student);
        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'academic_year' => 'nullable|string|max:16',
            'enrollment_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        $this->lifecycle->reEnroll($student, $data);

        return redirect()
            ->to(route('admin.students.show', $student) . '?tab=enrollment')
            ->with('success', 'تمت إعادة قيد الطالب بنجاح');
    }

    public function graduate(Request $request, User $student)
    {
        $this->authorize('lifecycle.graduate', $student);
        $data = $request->validate([
            'graduation_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        $this->lifecycle->graduate($student, $data);

        return redirect()
            ->to(route('admin.students.show', $student) . '?tab=enrollment')
            ->with('success', 'تم تخريج الطالب بنجاح');
    }

    public function changeStatus(Request $request, User $student)
    {
        $this->authorize('lifecycle.changeStatus', $student);
        $data = $request->validate([
            'status' => 'required|in:' . implode(',', \App\Support\Student\StudentStatus::all()),
            'effective_date' => 'nullable|date',
            'reason' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:2000',
        ]);

        $this->lifecycle->changeStatus($student, $data);

        return redirect()
            ->route('admin.students.show', $student)
            ->with('success', 'تم تحديث حالة الطالب بنجاح');
    }

    public function updateGuardians(Request $request, User $student)
    {
        $this->authorize('lifecycle.linkGuardian', $student);
        $data = $request->validate([
            'guardian_links' => 'required|array|min:1',
            'guardian_links.*.guardian_id' => 'required|integer|exists:users,id',
            'guardian_links.*.relationship_type' => 'nullable|in:' . implode(',', GuardianRelationship::types()),
            'guardian_links.*.is_primary' => 'nullable|boolean',
            'guardian_links.*.is_emergency_contact' => 'nullable|boolean',
            'guardian_links.*.is_pickup_authorized' => 'nullable|boolean',
            'guardian_links.*.is_financial_responsible' => 'nullable|boolean',
        ]);

        $this->lifecycle->updateGuardians($student, $data['guardian_links']);

        return redirect()
            ->to(route('admin.students.show', $student) . '?tab=family')
            ->with('success', 'تم تحديث بيانات أولياء الأمور بنجاح');
    }
}
