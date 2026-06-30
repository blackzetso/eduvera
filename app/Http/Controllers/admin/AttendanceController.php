<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceAlert;
use App\Models\AttendanceImportBatch;
use App\Models\AttendanceThreshold;
use App\Models\Category;
use App\Models\StudentAttendance;
use App\Models\TimetablePeriod;
use App\Models\User;
use App\Services\AttendanceImportService;
use App\Services\AttendanceRecordingService;
use App\Services\AttendanceStatsService;
use App\Services\AttendanceThresholdService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    public function dashboard(AttendanceStatsService $stats)
    {
        return Inertia::render('Admin/theme1/Attendance/Dashboard', [
            'kpis' => $stats->todayKpis(),
            'categories' => Category::where('status', 'enable')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function index(Request $request)
    {
        $query = StudentAttendance::query()
            ->with(['student:id,name,email', 'category:id,name'])
            ->orderByDesc('attendance_date');

        if ($request->filled('date')) {
            $query->whereDate('attendance_date', $request->date);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return Inertia::render('Admin/theme1/Attendance/Index', [
            'records' => $query->paginate(50)->withQueryString(),
            'filters' => $request->only(['date', 'category_id', 'status']),
            'categories' => Category::where('status', 'enable')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function markForm(Request $request)
    {
        $periodId = $request->get('period_id');
        $date = $request->get('date', today()->toDateString());

        $period = $periodId ? TimetablePeriod::with(['assignments.teacher', 'assignments.subject', 'category'])->findOrFail($periodId) : null;

        $students = collect();
        if ($period?->category_id) {
            $students = User::query()
                ->where('user_type', 'student')
                ->where('category_id', $period->category_id)
                ->orderBy('name')
                ->get(['id', 'name', 'student_code']);

            $existing = StudentAttendance::query()
                ->where('timetable_period_id', $period->id)
                ->whereDate('attendance_date', $date)
                ->get()
                ->keyBy('student_id');

            $students = $students->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'student_code' => $s->student_code,
                'status' => $existing->get($s->id)?->status ?? 'present',
                'notes' => $existing->get($s->id)?->notes,
            ]);
        }

        return Inertia::render('Admin/theme1/Attendance/Mark', [
            'period' => $period,
            'date' => $date,
            'students' => $students,
            'periods' => TimetablePeriod::with('category')->orderBy('period_number')->get(),
        ]);
    }

    public function mark(Request $request, AttendanceRecordingService $recording)
    {
        $validated = $request->validate([
            'timetable_period_id' => 'required|exists:timetable_periods,id',
            'attendance_date' => 'required|date',
            'marks' => 'required|array',
            'marks.*.student_id' => 'required|exists:users,id',
            'marks.*.status' => 'required|in:present,absent,late,excused',
            'marks.*.notes' => 'nullable|string|max:500',
        ]);

        $period = TimetablePeriod::findOrFail($validated['timetable_period_id']);
        $count = $recording->markClassAttendance(
            $period,
            $validated['attendance_date'],
            $validated['marks'],
            auth()->id()
        );

        return redirect()->route('admin.attendances.index')
            ->with('success', "تم حفظ حضور {$count} طالب.");
    }

    public function bulkUpload(Request $request, AttendanceImportService $importService)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx|max:10240',
            'attendance_date' => 'nullable|date',
            'session_type' => 'nullable|string|max:50',
        ]);

        $batch = $importService->processUpload(
            $request->file('file'),
            auth()->id(),
            'school',
            null,
            $validated['attendance_date'] ?? null,
            $validated['session_type'] ?? 'class',
        );

        return redirect()->route('admin.attendances.import.preview', $batch);
    }

    public function importPreview(AttendanceImportBatch $batch)
    {
        return Inertia::render('Admin/theme1/Attendance/ImportPreview', [
            'batch' => $batch,
            'errors' => $batch->validation_errors_json ?? [],
            'sample' => array_slice($batch->parsed_data_json ?? [], 0, 20),
        ]);
    }

    public function importConfirm(AttendanceImportBatch $batch, AttendanceImportService $importService)
    {
        $count = $importService->confirmImport($batch);

        return redirect()->route('admin.attendances.index')
            ->with('success', "تم استيراد {$count} سجل حضور.");
    }

    public function thresholds()
    {
        return Inertia::render('Admin/theme1/Attendance/ManageThresholds', [
            'thresholds' => AttendanceThreshold::with('category')->orderByDesc('id')->get(),
            'categories' => Category::where('status', 'enable')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeThreshold(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'academic_year' => 'nullable|string|max:255',
            'period_type' => 'required|in:term,year,custom',
            'warning_absences' => 'required|integer|min:1',
            'critical_absences' => 'required|integer|min:1|gte:warning_absences',
            'auto_notify_guardian' => 'boolean',
            'suggest_block_at_critical' => 'boolean',
        ]);

        AttendanceThreshold::create([
            ...$validated,
            'auto_notify_guardian' => $request->boolean('auto_notify_guardian', true),
            'suggest_block_at_critical' => $request->boolean('suggest_block_at_critical', true),
            'is_active' => true,
        ]);

        return back()->with('success', 'تم حفظ إعدادات العتبة.');
    }

    public function alerts(Request $request)
    {
        $alerts = AttendanceAlert::query()
            ->with('student:id,name')
            ->when($request->level, fn ($q) => $q->where('level', $request->level))
            ->orderByDesc('triggered_at')
            ->paginate(30);

        return Inertia::render('Admin/theme1/Attendance/Alerts', [
            'alerts' => $alerts,
            'filters' => $request->only('level'),
        ]);
    }

    public function acknowledgeAlert(Request $request, AttendanceAlert $alert)
    {
        $validated = $request->validate([
            'action_taken' => 'required|in:none,blocked,warning_sent,meeting_called,ignored',
            'notes' => 'nullable|string',
        ]);

        $alert->update([
            'acknowledged_by' => auth()->id(),
            'acknowledged_at' => now(),
            'action_taken' => $validated['action_taken'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'تم تسجيل الإجراء.');
    }

    public function studentTab(User $student)
    {
        abort_unless($student->user_type === 'student', 404);

        return redirect()->to(route('admin.students.show', $student).'?tab=attendance');
    }

    public function runThresholdCheck(AttendanceThresholdService $thresholdService)
    {
        $created = $thresholdService->checkAllStudents();

        return back()->with('success', "تم فحص العتبات. تنبيهات جديدة/محدّثة: {$created}");
    }
}
