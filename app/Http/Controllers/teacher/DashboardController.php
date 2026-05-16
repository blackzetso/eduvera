<?php

namespace App\Http\Controllers\teacher;

use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Models\LiveStream;

class DashboardController extends Controller
{
    public function index()
    {
        $teacher = auth()->user();

        $streams = LiveStream::where('teacher_email', $teacher->email)
            ->orderByDesc('start_datetime')
            ->limit(5)
            ->get(['id', 'title', 'subject', 'status', 'start_datetime', 'end_datetime']);

        $totalStreams    = LiveStream::where('teacher_email', $teacher->email)->count();
        $liveStreams     = LiveStream::where('teacher_email', $teacher->email)->where('status', 'live')->count();
        $scheduledStreams = LiveStream::where('teacher_email', $teacher->email)->where('status', 'scheduled')->count();

        return Inertia::render('Teacher/Dashboard/Index', [
            'recentStreams'    => $streams,
            'totalStreams'     => $totalStreams,
            'liveStreams'      => $liveStreams,
            'scheduledStreams' => $scheduledStreams,
            'teacher'         => [
                'name'  => $teacher->name,
                'email' => $teacher->email,
            ],
        ]);
    }
}
