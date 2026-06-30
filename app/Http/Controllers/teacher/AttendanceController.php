<?php

namespace App\Http\Controllers\teacher;

use App\Http\Controllers\Controller;
use App\Jobs\NotifyGuardiansOfClassAttendance;
use App\Models\StudentAttendance;
use App\Models\TimetableAssignment;
use App\Models\TimetablePeriod;
use App\Models\User;
use App\Services\AttendanceRecordingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $teacherId = auth()->id();

        $periodIds = TimetableAssignment::query()
            ->where('teacher_id', $teacherId)
            ->pluck('timetable_period_id');

        $periods = TimetablePeriod::query()
            ->whereIn('id', $periodIds)
            ->with(['category', 'assignments.subject'])
            ->orderBy('period_number')
            ->get();

        return Inertia::render('Teacher/theme1/Attendance/Index', [
            'periods' => $periods,
            'date' => $request->get('date', today()->toDateString()),
        ]);
    }

    public function class(TimetablePeriod $period, Request $request, AttendanceRecordingService $recording)
    {
        abort_unless($recording->teacherCanMarkPeriod(auth()->id(), $period), 403, 'غير مصرح لك بتسجيل حضور هذه الحصة.');

        $date = $request->get('date', today()->toDateString());

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

        $studentRows = $students->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'student_code' => $s->student_code,
            'status' => $existing->get($s->id)?->status ?? 'present',
            'notes' => $existing->get($s->id)?->notes,
        ]);

        return Inertia::render('Teacher/theme1/Attendance/Class', [
            'period' => $period->load(['category', 'assignments.subject']),
            'date' => $date,
            'students' => $studentRows,
        ]);
    }

    public function mark(Request $request, TimetablePeriod $period, AttendanceRecordingService $recording)
    {
        abort_unless($recording->teacherCanMarkPeriod(auth()->id(), $period), 403);

        $validated = $request->validate([
            'attendance_date' => 'required|date',
            'marks' => 'required|array',
            'marks.*.student_id' => 'required|exists:users,id',
            'marks.*.status' => 'required|in:present,absent,late,excused',
            'marks.*.notes' => 'nullable|string|max:500',
        ]);

        $count = $recording->markClassAttendance(
            $period,
            $validated['attendance_date'],
            $validated['marks'],
            auth()->id()
        );

        NotifyGuardiansOfClassAttendance::dispatch($period->id, $validated['attendance_date']);

        return redirect()->route('teacher.attendances.index')
            ->with('success', "تم حفظ حضور {$count} طالب.");
    }
}
