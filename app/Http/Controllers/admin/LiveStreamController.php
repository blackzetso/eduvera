<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\LiveStream;
use App\Models\LiveStreamAttendance;
use App\Models\StorageWallet;
use App\Services\TeamsService;
use App\Services\ZoomService;
use App\Services\LiveKitService;
use App\Services\GoogleMeetService;
use App\Services\HMSService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Carbon\Carbon;
use Exception;

class LiveStreamController extends Controller
{
    public function index()
    {
        $streams = LiveStream::withCount('attendances')
            ->orderByDesc('start_datetime')
            ->get()
            ->map(function ($stream) {
                return [
                    'id'                => $stream->id,
                    'title'             => $stream->title,
                    'teacher_name'      => $stream->teacher_name,
                    'subject'           => $stream->subject,
                    'start_datetime'    => $stream->start_datetime?->format('Y-m-d H:i'),
                    'end_datetime'      => $stream->end_datetime?->format('Y-m-d H:i'),
                    'status'            => $stream->status,
                    'provider'          => $stream->provider,
                    'join_url'          => $stream->join_url,
                    'guest_join_url'    => route('live-streams.guest-join', $stream->id),
                    'attendances_count' => $stream->attendances_count,
                ];
            });

        return Inertia::render('Admin/theme1/LiveStreams/Index', [
            'streams' => $streams,
        ]);
    }

    public function create()
    {
        $user = auth()->user();

        $maxDurationSetting = \App\Models\Setting::where('key', 'live_stream_max_duration')->first();
        $maxDuration = $maxDurationSetting ? (int) $maxDurationSetting->value : 60;

        return Inertia::render('Admin/theme1/LiveStreams/Create', [
            'teamsConfigured'      => (new TeamsService())->isConfigured(),
            'zoomConfigured'       => (new ZoomService())->isConfigured(),
            'livekitConfigured'    => (new LiveKitService())->isConfigured(),
            'googleMeetConfigured' => (new GoogleMeetService())->isConfigured(),
            'hmsConfigured'        => (new HMSService())->isConfigured(),
            'authUser'             => [
                'name'  => $user->name,
                'email' => $user->email,
            ],
            'maxDuration'          => $maxDuration,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'                => 'required|string|max:255',
            'description'          => 'nullable|string',
            'teacher_name'         => 'required|string|max:255',
            'teacher_email'        => 'nullable|email|max:255',
            'subject'              => 'nullable|string|max:255',
            'provider'             => 'required|in:none,livekit,teams,zoom,google_meet',
            'classroom_dashboard'  => 'required|in:jitsi,livekit,hms',
            'start_datetime'       => 'required|date|after:now',
        ]);
        $maxDuration = (int) (\App\Models\Setting::where('key', 'live_stream_max_duration')->first()?->value ?? 60);
        $validated['end_datetime'] = Carbon::parse($validated['start_datetime'])->addMinutes($maxDuration);

        $meetingId          = null;
        $zoomMeetingId      = null;
        $livekitRoomName    = null;
        $googleMeetSpace    = null;
        $hmsRoomId          = null;
        $joinUrl            = null;
        $provider           = $validated['provider'];
        $classDashboard     = $validated['classroom_dashboard'];

        // ── External streaming platform ───────────────────────────────────────
        if ($provider === 'livekit') {
            $lkService = new LiveKitService();
            if (!$lkService->isConfigured()) {
                return back()->withErrors(['provider' => 'يرجى إعداد بيانات LiveKit أولاً من الإعدادات.']);
            }
            try {
                $roomName        = Str::slug($validated['title']) . '-' . Str::random(8);
                $room            = $lkService->createRoom($roomName);
                $livekitRoomName = $room['roomName'];
                $joinUrl         = $room['joinUrl'];
            } catch (Exception $e) {
                return back()->withErrors(['provider' => 'فشل إنشاء غرفة LiveKit: ' . $e->getMessage()]);
            }

        } elseif ($provider === 'teams') {
            $service = new TeamsService();
            if (!$service->isConfigured()) {
                return back()->withErrors(['provider' => 'يرجى إعداد بيانات Microsoft Teams أولاً من الإعدادات.']);
            }
            try {
                $start   = Carbon::parse($validated['start_datetime'])->toIso8601String();
                $end     = $validated['end_datetime']
                    ? Carbon::parse($validated['end_datetime'])->toIso8601String()
                    : Carbon::parse($validated['start_datetime'])->addHour()->toIso8601String();

                $meeting   = $service->createMeeting($validated['title'], $start, $end);
                $meetingId = $meeting['meetingId'];
                $joinUrl   = $meeting['joinUrl'];
            } catch (Exception $e) {
                return back()->withErrors(['provider' => 'فشل إنشاء اجتماع Teams: ' . $e->getMessage()]);
            }

        } elseif ($provider === 'zoom') {
            $service = new ZoomService();
            if (!$service->isConfigured()) {
                return back()->withErrors(['provider' => 'يرجى إعداد بيانات Zoom أولاً من الإعدادات.']);
            }
            try {
                $start    = Carbon::parse($validated['start_datetime']);
                $end      = $validated['end_datetime']
                    ? Carbon::parse($validated['end_datetime'])
                    : $start->copy()->addHour();
                $duration = (int) $start->diffInMinutes($end);

                $meeting       = $service->createMeeting($validated['title'], $start->toIso8601String(), $duration);
                $zoomMeetingId = $meeting['meetingId'];
                $joinUrl       = $meeting['joinUrl'];
            } catch (Exception $e) {
                return back()->withErrors(['provider' => 'فشل إنشاء اجتماع Zoom: ' . $e->getMessage()]);
            }

        } elseif ($provider === 'google_meet') {
            $service = new GoogleMeetService();
            if (!$service->isConfigured()) {
                return back()->withErrors(['provider' => 'يرجى إعداد بيانات Google Meet أولاً من الإعدادات.']);
            }
            try {
                $space           = $service->createSpace();
                $googleMeetSpace = $space['spaceName'];
                $joinUrl         = $space['joinUrl'];
            } catch (Exception $e) {
                return back()->withErrors(['provider' => 'فشل إنشاء اجتماع Google Meet: ' . $e->getMessage()]);
            }
        }

        // ── Classroom dashboard room setup ────────────────────────────────────
        if ($classDashboard === 'livekit') {
            $service = new LiveKitService();
            if (!$service->isConfigured()) {
                return back()->withErrors(['classroom_dashboard' => 'يرجى إعداد بيانات LiveKit أولاً من الإعدادات.']);
            }
            try {
                $roomName        = Str::slug($validated['title']) . '-' . Str::random(8);
                $room            = $service->createRoom($roomName);
                $livekitRoomName = $room['roomName'];
            } catch (Exception $e) {
                return back()->withErrors(['classroom_dashboard' => 'فشل إنشاء غرفة LiveKit: ' . $e->getMessage()]);
            }

        } elseif ($classDashboard === 'hms') {
            $service = new HMSService();
            if (!$service->isConfigured()) {
                return back()->withErrors(['classroom_dashboard' => 'يرجى إعداد بيانات 100ms أولاً من الإعدادات.']);
            }
            try {
                $roomName  = 'eduvera-' . Str::random(10);
                $room      = $service->createRoom($roomName);
                $hmsRoomId = $room['roomId'];
            } catch (Exception $e) {
                return back()->withErrors(['classroom_dashboard' => 'فشل إنشاء غرفة 100ms: ' . $e->getMessage()]);
            }
        }

        $extraSession = filter_var($request->input('extra_session'), FILTER_VALIDATE_BOOLEAN);

        // Extra session: create immediately with double duration.
        // If admin rejects later, end_datetime will be cut back to normal.
        $endDatetime = $extraSession
            ? Carbon::parse($validated['start_datetime'])->addMinutes($maxDuration * 2)
            : ($validated['end_datetime'] ?? null);

        $stream = LiveStream::create([
            'title'                  => $validated['title'],
            'description'            => $validated['description'] ?? null,
            'teacher_name'           => $validated['teacher_name'],
            'teacher_email'          => $validated['teacher_email'] ?? null,
            'subject'                => $validated['subject'] ?? null,
            'start_datetime'         => $validated['start_datetime'],
            'end_datetime'           => $endDatetime,
            'provider'               => $provider,
            'classroom_dashboard'    => $classDashboard,
            'teams_meeting_id'       => $meetingId,
            'zoom_meeting_id'        => $zoomMeetingId,
            'livekit_room_name'      => $livekitRoomName,
            'google_meet_space_name' => $googleMeetSpace,
            'hms_room_id'            => $hmsRoomId,
            'join_url'               => $joinUrl,
            'status'                 => 'scheduled',
            'extra_session_status'   => $extraSession ? 'pending' : null,
        ]);

        // Set join_url for HMS after record creation (needs stream ID)
        if ($classDashboard === 'hms' && $hmsRoomId) {
            $stream->update(['join_url' => route('live-streams.guest-join', $stream->id)]);
        }

        return redirect()->route('admin.live-streams.index')
            ->with('success', $extraSession
                ? 'تم إنشاء البث وإرسال طلب الحصة الإضافية للأدمن للموافقة.'
                : 'تم إنشاء البث المباشر بنجاح.');
    }

    public function approveExtraSession(LiveStream $liveStream)
    {
        if ($liveStream->extra_session_status !== 'pending') {
            return back()->with('error', 'لا يوجد طلب معلّق لهذا البث.');
        }

        // end_datetime is already set to double duration at creation — just confirm
        $liveStream->update(['extra_session_status' => 'approved']);

        $maxDuration = (int) (\App\Models\Setting::where('key', 'live_stream_max_duration')->first()?->value ?? 60);

        return back()->with('success', 'تمت الموافقة — البث سيستمر لـ ' . ($maxDuration * 2) . ' دقيقة.');
    }

    public function cancelExtraSession(LiveStream $liveStream)
    {
        if ($liveStream->extra_session_status !== 'pending') {
            return back()->with('error', 'لا يوجد طلب معلّق لهذا البث.');
        }

        // Cut end_datetime back to normal duration (even if stream is already live)
        $maxDuration = (int) (\App\Models\Setting::where('key', 'live_stream_max_duration')->first()?->value ?? 60);
        $normalEnd   = Carbon::parse($liveStream->start_datetime)->addMinutes($maxDuration);

        $liveStream->update([
            'end_datetime'         => $normalEnd,
            'extra_session_status' => 'rejected',
        ]);

        return back()->with('success', 'تم رفض الحصة الإضافية — البث سيعمل بالمدة الأصلية ' . $maxDuration . ' دقيقة فقط.');
    }

    // ─── Live extension (during stream) ──────────────────────────────────────

    public function requestExtension(Request $request, LiveStream $liveStream)
    {
        $request->validate(['minutes' => 'required|in:10,15,20,25,30']);

        $minutes = (int) $request->minutes;

        $liveStream->update([
            'end_datetime'              => Carbon::parse($liveStream->end_datetime)->addMinutes($minutes),
            'pending_extension_minutes' => $minutes,
        ]);

        return response()->json([
            'success'       => true,
            'added_seconds' => $minutes * 60,
            'message'       => "تم تمديد البث بـ {$minutes} دقيقة — في انتظار موافقة الأدمن.",
        ]);
    }

    public function cancelExtension(LiveStream $liveStream)
    {
        if (!$liveStream->pending_extension_minutes) {
            return back()->with('error', 'لا يوجد طلب تمديد معلّق لهذا البث.');
        }

        $minutes = (int) $liveStream->pending_extension_minutes;

        $liveStream->update([
            'end_datetime'              => Carbon::parse($liveStream->end_datetime)->subMinutes($minutes),
            'pending_extension_minutes' => null,
        ]);

        return back()->with('success', "تم إلغاء تمديد البث بـ {$minutes} دقيقة.");
    }

    public function approveExtension(LiveStream $liveStream)
    {
        $liveStream->update(['pending_extension_minutes' => null]);
        return back()->with('success', 'تمت الموافقة على تمديد البث.');
    }

    public function remainingSeconds(LiveStream $liveStream)
    {
        $total = ($liveStream->start_datetime && $liveStream->end_datetime)
            ? (int) abs($liveStream->start_datetime->diffInSeconds($liveStream->end_datetime))
            : null;

        return response()->json(['seconds_until_end' => $total]);
    }

    public function show(LiveStream $liveStream)
    {
        $attendances = $liveStream->attendances()
            ->orderBy('join_time')
            ->get()
            ->map(function ($record) {
                return [
                    'id'                 => $record->id,
                    'student_name'       => $record->student_name,
                    'student_email'      => $record->student_email,
                    'join_time'          => $record->join_time?->format('H:i:s'),
                    'leave_time'         => $record->leave_time?->format('H:i:s'),
                    'duration_seconds'   => $record->duration_seconds,
                    'formatted_duration' => $record->formatted_duration,
                ];
            });

        $classDashboard    = $liveStream->classroom_dashboard ?? 'jitsi';
        $livekitTeacherUrl = null;
        if ($classDashboard === 'livekit' && $liveStream->livekit_room_name) {
            try {
                $livekitTeacherUrl = (new LiveKitService())->getTeacherJoinUrl($liveStream->livekit_room_name);
            } catch (Exception) {}
        }

        $quizzes = $liveStream->quizzes()
            ->withCount('answers')
            ->with('answers')
            ->orderBy('created_at')
            ->get()
            ->map(function ($q) {
                $correctCount = $q->answers->where('is_correct', true)->count();
                return [
                    'id'             => $q->id,
                    'question_text'  => $q->question_text,
                    'question_type'  => $q->question_type,
                    'options'        => $q->options,
                    'correct_answer' => $q->correct_answer,
                    'allow_multiple' => $q->allow_multiple,
                    'time_limit'     => $q->time_limit,
                    'status'         => $q->status,
                    'attachment_url' => $q->attachment_url,
                    'activated_at'   => $q->activated_at?->format('H:i:s'),
                    'closed_at'      => $q->closed_at?->format('H:i:s'),
                    'answers_count'  => $q->answers_count,
                    'correct_count'  => $correctCount,
                    'answers'        => $q->answers->map(fn ($a) => [
                        'id'           => $a->id,
                        'student_name' => $a->student_name,
                        'answer'       => $a->answer,
                        'correction'   => $a->correction,
                        'is_correct'   => $a->is_correct,
                        'submitted_at' => $a->submitted_at?->format('H:i:s'),
                    ]),
                ];
            });

        return Inertia::render('Admin/theme1/LiveStreams/Show', [
            'stream' => [
                'id'                     => $liveStream->id,
                'title'                  => $liveStream->title,
                'description'            => $liveStream->description,
                'teacher_name'           => $liveStream->teacher_name,
                'teacher_email'          => $liveStream->teacher_email,
                'subject'                => $liveStream->subject,
                'start_datetime'         => $liveStream->start_datetime?->format('Y-m-d H:i'),
                'end_datetime'           => $liveStream->end_datetime?->format('Y-m-d H:i'),
                'status'                 => $liveStream->status,
                'provider'               => $liveStream->provider,
                'classroom_dashboard'    => $classDashboard,
                'join_url'               => $liveStream->join_url,
                'teams_meeting_id'       => $liveStream->teams_meeting_id,
                'zoom_meeting_id'        => $liveStream->zoom_meeting_id,
                'livekit_room_name'      => $liveStream->livekit_room_name,
                'livekit_teacher_url'    => $livekitTeacherUrl,
                'google_meet_space_name' => $liveStream->google_meet_space_name,
                'hms_room_id'            => $liveStream->hms_room_id,
                'recording_type'         => $liveStream->recording_type   ?? 'none',
                'recording_status'       => $liveStream->recording_status ?? 'none',
                'recording_size_mb'      => $liveStream->recording_size_mb,
                'video_url'              => $liveStream->video_url,
                'watch_url'              => route('streams.watch', $liveStream->id),
            ],
            'attendances'       => $attendances,
            'attendances_count' => $attendances->count(),
            'quizzes'           => $quizzes,
        ]);
    }

    public function edit(LiveStream $liveStream)
    {
        $maxDuration = (int) (\App\Models\Setting::where('key', 'live_stream_max_duration')->first()?->value ?? 60);

        return Inertia::render('Admin/theme1/LiveStreams/Edit', [
            'stream' => [
                'id'                  => $liveStream->id,
                'title'               => $liveStream->title,
                'description'         => $liveStream->description,
                'teacher_name'        => $liveStream->teacher_name,
                'teacher_email'       => $liveStream->teacher_email,
                'subject'             => $liveStream->subject,
                'start_datetime'      => $liveStream->start_datetime?->format('Y-m-d\TH:i'),
                'status'              => $liveStream->status,
                'provider'            => $liveStream->provider ?? 'livekit',
                'classroom_dashboard' => $liveStream->classroom_dashboard ?? 'livekit',
                'join_url'            => $liveStream->join_url,
                'guest_join_url'      => route('live-streams.guest-join', $liveStream->id),
            ],
            'teamsConfigured'      => (new TeamsService())->isConfigured(),
            'zoomConfigured'       => (new ZoomService())->isConfigured(),
            'livekitConfigured'    => (new LiveKitService())->isConfigured(),
            'googleMeetConfigured' => (new GoogleMeetService())->isConfigured(),
            'hmsConfigured'        => (new HMSService())->isConfigured(),
            'maxDuration'          => $maxDuration,
        ]);
    }

    public function update(Request $request, LiveStream $liveStream)
    {
        $validated = $request->validate([
            'title'               => 'required|string|max:255',
            'description'         => 'nullable|string',
            'teacher_name'        => 'required|string|max:255',
            'teacher_email'       => 'nullable|email|max:255',
            'subject'             => 'nullable|string|max:255',
            'provider'            => 'required|in:none,livekit,teams,zoom,google_meet',
            'classroom_dashboard' => 'required|in:jitsi,livekit,hms',
            'start_datetime'      => 'required|date|after:now',
        ]);

        $maxDuration = (int) (\App\Models\Setting::where('key', 'live_stream_max_duration')->first()?->value ?? 60);
        $validated['end_datetime'] = Carbon::parse($validated['start_datetime'])->addMinutes($maxDuration);

        $liveStream->update($validated);

        return redirect()->route('admin.live-streams.index')
            ->with('success', 'تم تحديث البث المباشر بنجاح.');
    }

    public function destroy(LiveStream $liveStream)
    {
        match ($liveStream->provider) {
            'teams'      => $liveStream->teams_meeting_id       ? (new TeamsService())->deleteMeeting($liveStream->teams_meeting_id)        : null,
            'zoom'       => $liveStream->zoom_meeting_id        ? (new ZoomService())->deleteMeeting($liveStream->zoom_meeting_id)          : null,
            'livekit'    => $liveStream->livekit_room_name      ? (new LiveKitService())->deleteRoom($liveStream->livekit_room_name)        : null,
            'google_meet'=> $liveStream->google_meet_space_name ? (new GoogleMeetService())->endSpace($liveStream->google_meet_space_name)  : null,
            'hms'        => $liveStream->hms_room_id            ? (new HMSService())->endRoom($liveStream->hms_room_id)                    : null,
            default      => null,
        };

        // Clean up server-side recording file if it exists
        if ($liveStream->recording_path && Storage::disk('local')->exists($liveStream->recording_path)) {
            Storage::disk('local')->delete($liveStream->recording_path);
        }

        $liveStream->delete();

        return redirect()->route('admin.live-streams.index')
            ->with('success', 'تم حذف البث المباشر بنجاح.');
    }

    // ─── Room page (teacher enters the live room) ─────────────────────────────

    public function room(LiveStream $liveStream)
    {
        if ($liveStream->status === 'scheduled') {
            $maxDuration    = (int) (\App\Models\Setting::where('key', 'live_stream_max_duration')->first()?->value ?? 60);
            $allowedMinutes = ($liveStream->extra_session_status === 'approved') ? $maxDuration * 2 : $maxDuration;
            $liveStream->update([
                'status'         => 'live',
                'start_datetime' => now(),
                'end_datetime'   => now()->addMinutes($allowedMinutes),
            ]);
            $liveStream->refresh();
        }

        $user           = auth()->user();
        $jitsiRoom      = 'eduvera-' . $liveStream->id . '-' . Str::slug($liveStream->title, '-');
        $classDashboard = $liveStream->classroom_dashboard ?? 'jitsi';

        $livekitWsUrl = null;
        $livekitToken = null;

        if ($classDashboard === 'livekit' && $liveStream->livekit_room_name) {
            try {
                $lkService    = new LiveKitService();
                $serverUrl    = \App\Models\Setting::where('key', 'livekit_server_url')->value('value') ?? '';
                $livekitWsUrl = str_replace(['https://', 'http://'], ['wss://', 'ws://'], rtrim($serverUrl, '/'));
                $livekitToken = $lkService->generateJoinToken(
                    $liveStream->livekit_room_name,
                    $user->name,
                    true,
                    86400
                );
            } catch (Exception $e) {
                $livekitWsUrl = null;
                $livekitToken = null;
            }
        }

        // Calculate total scheduled duration (timezone-safe: uses diff between stored datetimes)
        // start_datetime and end_datetime are both stored in the same "local" offset so their
        // difference is always the correct configured duration, regardless of server timezone.
        $secondsUntilEnd = $liveStream->end_datetime
            ? (int) max(0, now()->diffInSeconds($liveStream->end_datetime, false))
            : null;

        // Use Unix timestamps — completely timezone-safe
        $elapsedSeconds = $liveStream->start_datetime
            ? (int) max(0, now()->timestamp - $liveStream->start_datetime->timestamp)
            : 0;

        $wmSettings = \App\Models\Setting::whereIn('key', [
            'live_stream_watermark', 'live_stream_watermark_position', 'live_stream_watermark_opacity', 'live_stream_watermark_size',
        ])->pluck('value', 'key');
        $wmPath    = $wmSettings->get('live_stream_watermark');
        $watermark = $wmPath ? [
            'url'      => asset('storage/' . $wmPath),
            'position' => $wmSettings->get('live_stream_watermark_position', 'bottom-right'),
            'opacity'  => (int) $wmSettings->get('live_stream_watermark_opacity', 20),
            'size'     => (int) $wmSettings->get('live_stream_watermark_size', 100),
        ] : null;

        return Inertia::render('Admin/theme1/LiveStreams/LiveRoom', [
            'stream' => [
                'id'                  => $liveStream->id,
                'title'               => $liveStream->title,
                'subject'             => $liveStream->subject,
                'teacher_name'        => $liveStream->teacher_name,
                'start_datetime'      => $liveStream->start_datetime?->format('Y-m-d H:i'),
                'seconds_until_end'   => $secondsUntilEnd,
                'elapsed_seconds'     => $elapsedSeconds,
                'status'              => $liveStream->status,
                'provider'            => $liveStream->provider,
                'classroom_dashboard' => $classDashboard,
                'hms_room_id'         => $liveStream->hms_room_id,
            ],
            'jitsiRoom'      => $jitsiRoom,
            'teacherName'    => $user->name,
            'studentJoinUrl' => route('live-streams.guest-join', $liveStream->id),
            'livekitWsUrl'   => $livekitWsUrl,
            'livekitToken'   => $livekitToken,
            'watermark'      => $watermark,
        ]);
    }

    // ─── Status update ────────────────────────────────────────────────────────

    public function updateStatus(Request $request, LiveStream $liveStream)
    {
        $request->validate([
            'status' => 'required|in:scheduled,live,ended',
        ]);

        $updateData = ['status' => $request->status];
        if ($request->status === 'ended') {
            $updateData['end_datetime'] = now();
        }
        $liveStream->update($updateData);

        $message = match ($request->status) {
            'live'      => 'بدأ البث المباشر بنجاح.',
            'ended'     => 'تم إنهاء البث المباشر.',
            'scheduled' => 'تم إعادة جدولة البث.',
        };

        return back()->with('success', $message);
    }

    // ─── Public guest join (students) ────────────────────────────────────────

    /**
     * Show the public guest join page for students.
     * No authentication required.
     */
    public function guestJoin(LiveStream $liveStream)
    {
        $jitsiRoom      = 'eduvera-' . $liveStream->id . '-' . Str::slug($liveStream->title, '-');
        $classDashboard = $liveStream->classroom_dashboard ?? 'jitsi';

        $livekitWsUrl = null;

        if ($classDashboard === 'livekit') {
            $serverUrl    = \App\Models\Setting::where('key', 'livekit_server_url')->value('value') ?? '';
            $livekitWsUrl = str_replace(['https://', 'http://'], ['wss://', 'ws://'], rtrim($serverUrl, '/'));
        }

        return Inertia::render('Student/JoinRoom', [
            'stream' => [
                'id'                  => $liveStream->id,
                'title'               => $liveStream->title,
                'subject'             => $liveStream->subject,
                'teacher_name'        => $liveStream->teacher_name,
                'status'              => $liveStream->status,
                'start_datetime'      => $liveStream->start_datetime?->format('Y-m-d H:i'),
                'provider'            => $liveStream->provider,
                'classroom_dashboard' => $classDashboard,
                'hms_room_id'         => $liveStream->hms_room_id,
            ],
            'jitsiRoom'    => $jitsiRoom,
            'livekitWsUrl' => $livekitWsUrl,
            'watermark'    => (function () {
                $wm = \App\Models\Setting::whereIn('key', [
                    'live_stream_watermark', 'live_stream_watermark_position', 'live_stream_watermark_opacity', 'live_stream_watermark_size',
                ])->pluck('value', 'key');
                $p = $wm->get('live_stream_watermark');
                return $p ? [
                    'url'      => asset('storage/' . $p),
                    'position' => $wm->get('live_stream_watermark_position', 'bottom-right'),
                    'opacity'  => (int) $wm->get('live_stream_watermark_opacity', 20),
                    'size'     => (int) $wm->get('live_stream_watermark_size', 100),
                ] : null;
            })(),
        ]);
    }

    public function guestStatus(LiveStream $liveStream)
    {
        return response()->json([
            'status' => $liveStream->status,
        ]);
    }

    /**
     * Generate a LiveKit student token by name (called from JoinRoom frontend).
     */
    public function guestLivekitToken(Request $request, LiveStream $liveStream)
    {
        $request->validate(['name' => 'required|string|max:100']);

        if (($liveStream->classroom_dashboard ?? 'jitsi') !== 'livekit' || !$liveStream->livekit_room_name) {
            return response()->json(['error' => 'هذا البث لا يستخدم LiveKit.'], 422);
        }

        if ($liveStream->status === 'ended') {
            return response()->json(['error' => 'انتهى هذا البث.'], 403);
        }

        try {
            $lkService = new LiveKitService();
            $token     = $lkService->generateJoinToken(
                $liveStream->livekit_room_name,
                $request->input('name'),
                true,
                86400
            );
            return response()->json(['token' => $token]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate a student auth token for the HMS room.
     * Called by the frontend via fetch (not Inertia).
     */
    public function guestToken(Request $request, LiveStream $liveStream)
    {
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        if (!$liveStream->hms_room_id) {
            return response()->json(['error' => 'غرفة البث غير جاهزة بعد.'], 404);
        }

        if ($liveStream->status === 'ended') {
            return response()->json(['error' => 'انتهى هذا البث.'], 403);
        }

        try {
            $service = new HMSService();
            $token = $service->generateAuthToken(
                $liveStream->hms_room_id,
                'guest-' . Str::random(8),
                'student',
                $request->input('name')
            );

            return response()->json([
                'token'   => $token,
                'roomId'  => $liveStream->hms_room_id,
                'stream'  => [
                    'title'        => $liveStream->title,
                    'teacher_name' => $liveStream->teacher_name,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ─── Sync attendance ──────────────────────────────────────────────────────

    public function syncAttendance(LiveStream $liveStream)
    {
        $provider = $liveStream->provider;

        if ($provider === 'none') {
            return back()->withErrors(['sync' => 'هذا البث غير مرتبط بأي منصة. لا يمكن مزامنة الحضور.']);
        }

        try {
            if ($provider === 'teams') {
                if (!$liveStream->teams_meeting_id) {
                    return back()->withErrors(['sync' => 'لا يوجد اجتماع Teams مرتبط بهذا البث.']);
                }
                $service = new TeamsService();
                if (!$service->isConfigured()) {
                    return back()->withErrors(['sync' => 'يرجى إعداد بيانات Microsoft Teams أولاً.']);
                }
                $attendees = $service->syncAttendance($liveStream->teams_meeting_id);

            } elseif ($provider === 'zoom') {
                if (!$liveStream->zoom_meeting_id) {
                    return back()->withErrors(['sync' => 'لا يوجد اجتماع Zoom مرتبط بهذا البث.']);
                }
                $service = new ZoomService();
                if (!$service->isConfigured()) {
                    return back()->withErrors(['sync' => 'يرجى إعداد بيانات Zoom أولاً.']);
                }
                $attendees = $service->syncAttendance($liveStream->zoom_meeting_id);

            } elseif ($provider === 'livekit') {
                if (!$liveStream->livekit_room_name) {
                    return back()->withErrors(['sync' => 'لا توجد غرفة LiveKit مرتبطة بهذا البث.']);
                }
                $service = new LiveKitService();
                if (!$service->isConfigured()) {
                    return back()->withErrors(['sync' => 'يرجى إعداد بيانات LiveKit أولاً.']);
                }
                $attendees = $service->listParticipants($liveStream->livekit_room_name);

            } elseif ($provider === 'google_meet') {
                if (!$liveStream->google_meet_space_name) {
                    return back()->withErrors(['sync' => 'لا يوجد اجتماع Google Meet مرتبط بهذا البث.']);
                }
                $service = new GoogleMeetService();
                if (!$service->isConfigured()) {
                    return back()->withErrors(['sync' => 'يرجى إعداد بيانات Google Meet أولاً.']);
                }
                $attendees = $service->syncAttendance($liveStream->google_meet_space_name);

            } else { // hms
                return back()->withErrors(['sync' => 'مزامنة الحضور لـ 100ms يتم تلقائياً عبر الغرفة المباشرة.']);
            }
        } catch (Exception $e) {
            return back()->withErrors(['sync' => 'فشل مزامنة الحضور: ' . $e->getMessage()]);
        }

        $liveStream->attendances()->delete();

        foreach ($attendees as $attendee) {
            LiveStreamAttendance::create([
                'live_stream_id'   => $liveStream->id,
                'student_name'     => $attendee['student_name'],
                'student_email'    => $attendee['student_email'],
                'join_time'        => $attendee['join_time'],
                'leave_time'       => $attendee['leave_time'],
                'duration_seconds' => $attendee['duration_seconds'],
            ]);
        }

        return back()->with('success', 'تم مزامنة بيانات الحضور بنجاح. (' . count($attendees) . ' طالب)');
    }

    public function uploadRecording(Request $request, LiveStream $liveStream)
    {
        $request->validate([
            'recording' => 'required|file|max:2097152',
        ]);

        $file   = $request->file('recording');
        $sizeMb = round($file->getSize() / 1024 / 1024, 3);
        $sizeGb = $sizeMb / 1024;

        // Deduct from StorageWallet (track usage; never block upload)
        $wallet = StorageWallet::first();
        if ($wallet) {
            $wallet->deduct($sizeGb, 'تسجيل حصة: ' . $liveStream->title, $liveStream);
        }

        // Delete old recording file if any
        if ($liveStream->recording_path && Storage::disk('local')->exists($liveStream->recording_path)) {
            Storage::disk('local')->delete($liveStream->recording_path);
        }

        $path = $file->storeAs('recordings', $liveStream->id . '-' . now()->timestamp . '.webm', 'local');

        $liveStream->update([
            'recording_type'    => 'server',
            'recording_status'  => 'ready',
            'recording_path'    => $path,
            'recording_size_mb' => $sizeMb,
        ]);

        return response()->json(['success' => true, 'size_mb' => $sizeMb]);
    }

    public function uploadWbMedia(Request $request, LiveStream $liveStream)
    {
        $request->validate([
            'file' => 'required|file|mimes:mp4,webm,ogg,mov|max:524288',
        ]);
        $file = $request->file('file');
        $name = $liveStream->id . '-wb-' . now()->timestamp . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('wb-media', $name, 'public');
        return response()->json(['success' => true, 'url' => asset('storage/' . $path)]);
    }

    public function submitVideoUrl(Request $request, LiveStream $liveStream)
    {
        $request->validate([
            'video_url' => [
                'required',
                'string',
                'max:500',
                function ($attr, $value, $fail) {
                    if (!preg_match('/(?:youtube\.com\/(?:watch\?.*v=|embed\/|shorts\/)|youtu\.be\/)[\w\-]+/', $value)) {
                        $fail('يجب أن يكون الرابط من YouTube (youtube.com أو youtu.be).');
                    }
                },
            ],
        ]);

        $liveStream->update([
            'video_url'      => $request->video_url,
            'recording_type' => 'local',
        ]);

        return back()->with('success', 'تم حفظ رابط الفيديو بنجاح.');
    }
}
