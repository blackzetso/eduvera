<?php

namespace App\Http\Controllers\teacher;

use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Models\LiveStream;
use App\Models\TimetableAssignment;
use Carbon\Carbon;

class DashboardController extends Controller
{
    // Arabic day names matching TimetableDay.day_name values
    private const ARABIC_DAYS = [
        0 => 'الأحد',
        1 => 'الإثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت',
    ];

    public function index()
    {
        $teacher = auth()->user();

        $streams = LiveStream::where('teacher_email', $teacher->email)
            ->orderByDesc('start_datetime')
            ->limit(5)
            ->get(['id', 'title', 'subject', 'status', 'start_datetime', 'end_datetime']);

        $totalStreams     = LiveStream::where('teacher_email', $teacher->email)->count();
        $liveStreams      = LiveStream::where('teacher_email', $teacher->email)->where('status', 'live')->count();
        $scheduledStreams = LiveStream::where('teacher_email', $teacher->email)->where('status', 'scheduled')->count();

        // Today's schedule for the logged-in teacher
        $todayArabic = self::ARABIC_DAYS[Carbon::today()->dayOfWeek] ?? null;

        $todaySchedule = [];
        if ($todayArabic) {
            $todaySchedule = TimetableAssignment::where('teacher_id', $teacher->id)
                ->whereHas('period.day', fn($q) => $q->where('day_name', $todayArabic))
                ->with([
                    'period',
                    'period.day',
                    'period.category',
                    'subject',
                ])
                ->get()
                ->sortBy('period.period_number')
                ->values()
                ->toArray();
        }

        return Inertia::render('Teacher/Dashboard/Index', [
            'recentStreams'    => $streams,
            'totalStreams'     => $totalStreams,
            'liveStreams'      => $liveStreams,
            'scheduledStreams' => $scheduledStreams,
            'todaySchedule'   => $todaySchedule,
            'todayDayName'    => $todayArabic,
            'teacher'         => [
                'name'  => $teacher->name,
                'email' => $teacher->email,
            ],
        ]);
    }
}
