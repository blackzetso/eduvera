<?php

namespace App\Http\Controllers\teacher;

use Inertia\Inertia;
use App\Models\User;
use App\Models\Subject;
use App\Models\Timetable;
use App\Models\TimetablePeriod;
use App\Models\TimetableAssignment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TimetableController extends Controller
{
    /**
     * Display the teacher's timetable.
     */
    public function index(Request $request)
    {
        $teacherId = auth()->id();

        // Get the single timetable
        $timetable = Timetable::where('status', 'active')
            ->with([
                'days.periods.category',
                'days.periods.assignments' => function($query) use ($teacherId) {
                    $query->where('teacher_id', $teacherId);
                },
                'days.periods.assignments.subject',
                'periods.category',
                'periods.assignments' => function($query) use ($teacherId) {
                    $query->where('teacher_id', $teacherId);
                },
                'periods.assignments.subject',
            ])
            ->first();

        // If no timetable exists, return empty data
        if (!$timetable) {
            return Inertia::render('Teacher/Timetable/Index', [
                'timetable' => null,
                'myAssignments' => [],
                'availablePeriods' => [],
                'lessons' => [],
            ]);
        }

        // Get all periods with assignments for this teacher
        $myAssignments = TimetableAssignment::where('teacher_id', $teacherId)
            ->with(['period.timetable', 'period.day', 'period.category', 'subject'])
            ->get();

        // Get available periods (not assigned or assigned to this teacher)
        $availablePeriods = TimetablePeriod::whereHas('timetable', function($query) {
                $query->where('status', 'active');
            })
            ->with(['timetable', 'day', 'category', 'assignments.teacher'])
            ->get()
            ->filter(function($period) use ($teacherId) {
                // Show if not assigned or assigned to this teacher
                return $period->assignments->isEmpty() ||
                       $period->assignments->where('teacher_id', $teacherId)->isNotEmpty();
            })->values();

        $subjects = auth()->user()->subjects;

        return Inertia::render('Teacher/Timetable/Index', [
            'timetable' => $timetable,
            'myAssignments' => $myAssignments,
            'availablePeriods' => $availablePeriods,
            'subjects' => $subjects,
        ]);
    }

    /**
     * Teacher assigns themselves to a period.
     */
    public function assignSelf(Request $request)
    {
        $teacherId = auth()->id();

        $data = $request->validate([
            'timetable_period_id' => 'required|exists:timetable_periods,id',
            'subject_id' => [
                'required',
                'exists:subjects,id',
                function ($attribute, $value, $fail) use ($teacherId) {
                    $hasSubject = \Illuminate\Support\Facades\DB::table('subject_user')
                        ->where('user_id', $teacherId)
                        ->where('subject_id', $value)
                        ->exists();
                    if (!$hasSubject) {
                        $fail('أنت غير مسجل لتدريس هذه المادة.');
                    }
                },
            ],
        ]);

        // Check if teacher is already assigned to this period
        $existing = TimetableAssignment::where('timetable_period_id', $data['timetable_period_id'])
            ->where('teacher_id', $teacherId)
            ->first();

        if ($existing) {
            return back()->withErrors([
                'timetable_period_id' => 'أنت معين بالفعل على هذه الحصة'
            ]);
        }

        // Check if period is already assigned to another teacher
        $periodAssigned = TimetableAssignment::where('timetable_period_id', $data['timetable_period_id'])
            ->where('teacher_id', '!=', $teacherId)
            ->first();

        if ($periodAssigned) {
            return back()->withErrors([
                'timetable_period_id' => 'هذه الحصة معينة لمدرس آخر'
            ]);
        }

        // Check for conflicts - same teacher, same time, different period
        $period = TimetablePeriod::with(['timetable', 'day', 'category'])->findOrFail($data['timetable_period_id']);

        $conflict = TimetableAssignment::whereHas('period', function($query) use ($period, $teacherId) {
            $query->where('timetable_id', $period->timetable_id)
                ->where('timetable_day_id', $period->timetable_day_id)
                ->where('teacher_id', $teacherId)
                ->where(function($q) use ($period) {
                    // Two time ranges overlap if: (start1 < end2) AND (start2 < end1)
                    $q->where('time_from', '<', $period->time_to)
                      ->where('time_to', '>', $period->time_from);
                });
        })->first();

        if ($conflict) {
            return back()->withErrors([
                'timetable_period_id' => 'لديك حصة أخرى في نفس الوقت'
            ]);
        }

        // Get teacher and subject for notification
        $teacher = User::findOrFail($teacherId);
        $subject = Subject::findOrFail($data['subject_id']);

        TimetableAssignment::create([
            'timetable_period_id' => $data['timetable_period_id'],
            'teacher_id' => $teacherId,
            'subject_id' => $data['subject_id'],
            'assigned_by' => $teacherId,
            'status' => 'approved', // Auto-approve as per plan
        ]);



        return back()->with('success', 'تم إضافة نفسك للحصة بنجاح');
    }

    /**
     * Teacher removes themselves from an assignment.
     */
    public function removeSelfAssignment($id)
    {
        $teacherId = auth()->id();

        $assignment = TimetableAssignment::where('id', $id)
            ->where('teacher_id', $teacherId)
            ->firstOrFail();

        $assignment->delete();

        return back()->with('success', 'تم إزالة نفسك من الحصة بنجاح');
    }
}

