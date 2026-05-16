<?php

namespace App\Http\Controllers\teacher;

use App\Http\Controllers\Controller;
use App\Models\LiveStream;
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
    /** Ensure the live stream belongs to the logged-in teacher. */
    private function authorize(LiveStream $liveStream): void
    {
        abort_if($liveStream->teacher_email !== auth()->user()->email, 403, 'هذا البث لا يخصك.');
    }

    public function index()
    {
        $streams = LiveStream::where('teacher_email', auth()->user()->email)
            ->withCount('attendances')
            ->orderByDesc('start_datetime')
            ->get()
            ->map(fn($s) => [
                'id'                => $s->id,
                'title'             => $s->title,
                'teacher_name'      => $s->teacher_name,
                'subject'           => $s->subject,
                'start_datetime'    => $s->start_datetime?->format('Y-m-d H:i'),
                'end_datetime'      => $s->end_datetime?->format('Y-m-d H:i'),
                'status'            => $s->status,
                'provider'          => $s->provider,
                'join_url'          => $s->join_url,
                'guest_join_url'    => route('live-streams.guest-join', $s->id),
                'attendances_count' => $s->attendances_count,
            ]);

        return Inertia::render('Teacher/LiveStreams/Index', [
            'streams' => $streams,
        ]);
    }

    public function create()
    {
        $maxDuration = (int) (\App\Models\Setting::where('key', 'live_stream_max_duration')->first()?->value ?? 60);

        $categories = \App\Models\Category::whereNull('parent_id')
            ->where('status', 'enable')
            ->with(['children' => fn($q) => $q->where('status', 'enable')->orderBy('name')
                ->with(['children' => fn($q2) => $q2->where('status', 'enable')->orderBy('name')
                    ->with(['children' => fn($q3) => $q3->where('status', 'enable')->orderBy('name')])
                ])
            ])
            ->orderBy('name')
            ->get()
            ->map(fn($c) => [
                'id'       => $c->id,
                'name'     => $c->name,
                'children' => $c->children->map(fn($ch) => [
                    'id'       => $ch->id,
                    'name'     => $ch->name,
                    'children' => $ch->children->map(fn($gch) => [
                        'id'       => $gch->id,
                        'name'     => $gch->name,
                        'children' => $gch->children->map(fn($sgch) => ['id' => $sgch->id, 'name' => $sgch->name])->values(),
                    ])->values(),
                ])->values(),
            ]);

        return Inertia::render('Teacher/LiveStreams/Create', [
            'teamsConfigured'      => (new TeamsService())->isConfigured(),
            'zoomConfigured'       => (new ZoomService())->isConfigured(),
            'livekitConfigured'    => (new LiveKitService())->isConfigured(),
            'googleMeetConfigured' => (new GoogleMeetService())->isConfigured(),
            'hmsConfigured'        => (new HMSService())->isConfigured(),
            'maxDuration'          => $maxDuration,
            'categories'           => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'               => 'required|string|max:255',
            'description'         => 'nullable|string',
            'learning_points'     => 'nullable|array|max:20',
            'learning_points.*'   => 'nullable|string|max:255',
            'subject'             => 'nullable|string|max:255',
            'provider'            => 'required|in:none,livekit,teams,zoom,google_meet',
            'classroom_dashboard' => 'required|in:jitsi,livekit,hms',
            'start_datetime'      => 'required|date|after:now',
            'category_id'         => 'nullable|exists:categories,id',
            'thumbnail'           => 'nullable|image|max:2048',
        ]);

        // Force teacher identity from auth — never trust form input
        $teacher = auth()->user();
        $validated['teacher_name']  = $teacher->name;
        $validated['teacher_email'] = $teacher->email;

        $maxDuration = (int) (\App\Models\Setting::where('key', 'live_stream_max_duration')->first()?->value ?? 60);
        $validated['end_datetime'] = Carbon::parse($validated['start_datetime'])->addMinutes($maxDuration);

        $meetingId       = null;
        $zoomMeetingId   = null;
        $livekitRoomName = null;
        $googleMeetSpace = null;
        $hmsRoomId       = null;
        $joinUrl         = null;
        $provider        = $validated['provider'];
        $classDashboard  = $validated['classroom_dashboard'];

        // ── External streaming platform ──────────────────────────────────────
        if ($provider === 'livekit') {
            $lkService = new LiveKitService();
            if (!$lkService->isConfigured()) {
                return back()->withErrors(['provider' => 'يرجى إعداد بيانات LiveKit أولاً من الإعدادات.']);
            }
            try {
                $room            = $lkService->createRoom(Str::slug($validated['title']) . '-' . Str::random(8));
                $livekitRoomName = $room['roomName'];
                $joinUrl         = $room['joinUrl'];
            } catch (Exception $e) {
                return back()->withErrors(['provider' => 'فشل إنشاء غرفة LiveKit: ' . $e->getMessage()]);
            }

        } elseif ($provider === 'teams') {
            $service = new TeamsService();
            if (!$service->isConfigured()) {
                return back()->withErrors(['provider' => 'يرجى إعداد بيانات Microsoft Teams أولاً.']);
            }
            try {
                $start   = Carbon::parse($validated['start_datetime'])->toIso8601String();
                $end     = Carbon::parse($validated['end_datetime'])->toIso8601String();
                $meeting = $service->createMeeting($validated['title'], $start, $end);
                $meetingId = $meeting['meetingId'];
                $joinUrl   = $meeting['joinUrl'];
            } catch (Exception $e) {
                return back()->withErrors(['provider' => 'فشل إنشاء اجتماع Teams: ' . $e->getMessage()]);
            }

        } elseif ($provider === 'zoom') {
            $service = new ZoomService();
            if (!$service->isConfigured()) {
                return back()->withErrors(['provider' => 'يرجى إعداد بيانات Zoom أولاً.']);
            }
            try {
                $start    = Carbon::parse($validated['start_datetime']);
                $end      = Carbon::parse($validated['end_datetime']);
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
                return back()->withErrors(['provider' => 'يرجى إعداد بيانات Google Meet أولاً.']);
            }
            try {
                $space           = $service->createSpace();
                $googleMeetSpace = $space['spaceName'];
                $joinUrl         = $space['joinUrl'];
            } catch (Exception $e) {
                return back()->withErrors(['provider' => 'فشل إنشاء اجتماع Google Meet: ' . $e->getMessage()]);
            }
        }

        // ── Classroom dashboard room ──────────────────────────────────────────
        if ($classDashboard === 'livekit' && $provider !== 'livekit') {
            $service = new LiveKitService();
            if (!$service->isConfigured()) {
                return back()->withErrors(['classroom_dashboard' => 'يرجى إعداد بيانات LiveKit أولاً.']);
            }
            try {
                $room            = $service->createRoom(Str::slug($validated['title']) . '-' . Str::random(8));
                $livekitRoomName = $room['roomName'];
            } catch (Exception $e) {
                return back()->withErrors(['classroom_dashboard' => 'فشل إنشاء غرفة LiveKit: ' . $e->getMessage()]);
            }

        } elseif ($classDashboard === 'hms') {
            $service = new HMSService();
            if (!$service->isConfigured()) {
                return back()->withErrors(['classroom_dashboard' => 'يرجى إعداد بيانات 100ms أولاً.']);
            }
            try {
                $room      = $service->createRoom('eduvera-' . Str::random(10));
                $hmsRoomId = $room['roomId'];
            } catch (Exception $e) {
                return back()->withErrors(['classroom_dashboard' => 'فشل إنشاء غرفة 100ms: ' . $e->getMessage()]);
            }
        }

        $extraSession = filter_var($request->input('extra_session'), FILTER_VALIDATE_BOOLEAN);
        $endDatetime  = $extraSession
            ? Carbon::parse($validated['start_datetime'])->addMinutes($maxDuration * 2)
            : ($validated['end_datetime'] ?? null);

        $stream = LiveStream::create([
            'title'                  => $validated['title'],
            'description'            => $validated['description'] ?? null,
            'learning_points'        => array_values(array_filter($validated['learning_points'] ?? [], fn($v) => trim((string)$v) !== '')),
            'thumbnail_path'         => $request->hasFile('thumbnail') ? $request->file('thumbnail')->store('live-streams/thumbnails', 'public') : null,
            'teacher_name'           => $validated['teacher_name'],
            'teacher_email'          => $validated['teacher_email'],
            'subject'                => $validated['subject'] ?? null,
            'category_id'            => $validated['category_id'] ?? null,
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

        if ($classDashboard === 'hms' && $hmsRoomId) {
            $stream->update(['join_url' => route('live-streams.guest-join', $stream->id)]);
        }

        return redirect()->route('teacher.live-streams.index')
            ->with('success', $extraSession
                ? 'تم إنشاء البث وإرسال طلب الحصة الإضافية للأدمن للموافقة.'
                : 'تم إنشاء البث المباشر بنجاح.');
    }

    public function show(LiveStream $liveStream)
    {
        $this->authorize($liveStream);

        $attendances = $liveStream->attendances()
            ->orderBy('join_time')
            ->get()
            ->map(fn($r) => [
                'id'               => $r->id,
                'student_name'     => $r->student_name,
                'student_email'    => $r->student_email,
                'join_time'        => $r->join_time?->format('H:i:s'),
                'leave_time'       => $r->leave_time?->format('H:i:s'),
                'duration_seconds' => $r->duration_seconds,
            ]);

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
                    'status'         => $q->status,
                    'attachment_url' => $q->attachment_url,
                    'answers_count'  => $q->answers_count,
                    'correct_count'  => $correctCount,
                    'answers'        => $q->answers->map(fn($a) => [
                        'id'           => $a->id,
                        'student_name' => $a->student_name,
                        'answer'       => $a->answer,
                        'correction'   => $a->correction,
                        'is_correct'   => $a->is_correct,
                        'submitted_at' => $a->submitted_at?->format('H:i:s'),
                    ]),
                ];
            });

        return Inertia::render('Teacher/LiveStreams/Show', [
            'stream' => [
                'id'                  => $liveStream->id,
                'title'               => $liveStream->title,
                'description'         => $liveStream->description,
                'teacher_name'        => $liveStream->teacher_name,
                'teacher_email'       => $liveStream->teacher_email,
                'subject'             => $liveStream->subject,
                'start_datetime'      => $liveStream->start_datetime?->format('Y-m-d H:i'),
                'end_datetime'        => $liveStream->end_datetime?->format('Y-m-d H:i'),
                'status'              => $liveStream->status,
                'provider'            => $liveStream->provider,
                'classroom_dashboard' => $liveStream->classroom_dashboard ?? 'jitsi',
                'join_url'            => $liveStream->join_url,
                'guest_join_url'      => route('live-streams.guest-join', $liveStream->id),
                'recording_type'      => $liveStream->recording_type   ?? 'none',
                'recording_status'    => $liveStream->recording_status ?? 'none',
                'recording_size_mb'   => $liveStream->recording_size_mb,
                'video_url'           => $liveStream->video_url,
                'watch_url'           => route('streams.watch', $liveStream->id),
            ],
            'attendances'       => $attendances,
            'attendances_count' => $attendances->count(),
            'quizzes'           => $quizzes,
        ]);
    }

    public function edit(LiveStream $liveStream)
    {
        $this->authorize($liveStream);

        $maxDuration = (int) (\App\Models\Setting::where('key', 'live_stream_max_duration')->first()?->value ?? 60);

        $categories = \App\Models\Category::whereNull('parent_id')
            ->where('status', 'enable')
            ->with(['children' => fn($q) => $q->where('status', 'enable')->orderBy('name')
                ->with(['children' => fn($q2) => $q2->where('status', 'enable')->orderBy('name')
                    ->with(['children' => fn($q3) => $q3->where('status', 'enable')->orderBy('name')])
                ])
            ])
            ->orderBy('name')
            ->get()
            ->map(fn($c) => [
                'id'       => $c->id,
                'name'     => $c->name,
                'children' => $c->children->map(fn($ch) => [
                    'id'       => $ch->id,
                    'name'     => $ch->name,
                    'children' => $ch->children->map(fn($gch) => [
                        'id'       => $gch->id,
                        'name'     => $gch->name,
                        'children' => $gch->children->map(fn($sgch) => ['id' => $sgch->id, 'name' => $sgch->name])->values(),
                    ])->values(),
                ])->values(),
            ]);

        return Inertia::render('Teacher/LiveStreams/Edit', [
            'stream' => [
                'id'                  => $liveStream->id,
                'title'               => $liveStream->title,
                'description'         => $liveStream->description,
                'learning_points'     => $liveStream->learning_points ?? [],
                'subject'             => $liveStream->subject,
                'start_datetime'      => $liveStream->start_datetime?->format('Y-m-d\TH:i'),
                'status'              => $liveStream->status,
                'provider'            => $liveStream->provider ?? 'livekit',
                'classroom_dashboard' => $liveStream->classroom_dashboard ?? 'livekit',
                'join_url'            => $liveStream->join_url,
                'guest_join_url'      => route('live-streams.guest-join', $liveStream->id),
                'thumbnail_url'       => $liveStream->thumbnail_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($liveStream->thumbnail_path) : null,
                'video_url'           => $liveStream->video_url,
                'category_id'         => $liveStream->category_id,
            ],
            'categories'           => $categories,
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
        $this->authorize($liveStream);

        $validated = $request->validate([
            'title'               => 'required|string|max:255',
            'description'         => 'nullable|string',
            'learning_points'     => 'nullable|array|max:20',
            'learning_points.*'   => 'nullable|string|max:255',
            'subject'             => 'nullable|string|max:255',
            'start_datetime'      => ['required', 'date', $liveStream->status === 'ended' ? 'nullable' : 'after:now'],
            'category_id'         => 'nullable|exists:categories,id',
            'thumbnail'           => 'nullable|image|max:2048',
            'video_url'           => 'nullable|url|max:2048',
        ]);

        $maxDuration = (int) (\App\Models\Setting::where('key', 'live_stream_max_duration')->first()?->value ?? 60);

        $updateData = [
            'title'          => $validated['title'],
            'description'    => $validated['description'] ?? $liveStream->description,
            'learning_points'=> array_values(array_filter($validated['learning_points'] ?? [], fn($v) => trim((string)$v) !== '')),
            'subject'        => $validated['subject'] ?? $liveStream->subject,
            'start_datetime' => $validated['start_datetime'],
            'end_datetime'   => Carbon::parse($validated['start_datetime'])->addMinutes($maxDuration),
            'video_url'      => $validated['video_url'] ?? null,
            'category_id'    => $validated['category_id'] ?? $liveStream->category_id,
        ];

        if ($request->hasFile('thumbnail')) {
            if ($liveStream->thumbnail_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($liveStream->thumbnail_path);
            }
            $updateData['thumbnail_path'] = $request->file('thumbnail')->store('live-streams/thumbnails', 'public');
        }

        $liveStream->update($updateData);

        return redirect()->route('teacher.live-streams.index')
            ->with('success', 'تم تحديث البث المباشر بنجاح.');
    }

    public function destroy(LiveStream $liveStream)
    {
        $this->authorize($liveStream);

        match ($liveStream->provider) {
            'teams'       => $liveStream->teams_meeting_id       ? (new TeamsService())->deleteMeeting($liveStream->teams_meeting_id)       : null,
            'zoom'        => $liveStream->zoom_meeting_id        ? (new ZoomService())->deleteMeeting($liveStream->zoom_meeting_id)         : null,
            'livekit'     => $liveStream->livekit_room_name      ? (new LiveKitService())->deleteRoom($liveStream->livekit_room_name)       : null,
            'google_meet' => $liveStream->google_meet_space_name ? (new GoogleMeetService())->endSpace($liveStream->google_meet_space_name) : null,
            'hms'         => $liveStream->hms_room_id            ? (new HMSService())->endRoom($liveStream->hms_room_id)                   : null,
            default       => null,
        };

        // Clean up server-side recording file if it exists
        if ($liveStream->recording_path && Storage::disk('local')->exists($liveStream->recording_path)) {
            Storage::disk('local')->delete($liveStream->recording_path);
        }

        $liveStream->delete();

        return redirect()->route('teacher.live-streams.index')
            ->with('success', 'تم حذف البث المباشر بنجاح.');
    }

    public function room(LiveStream $liveStream)
    {
        $this->authorize($liveStream);

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
        $classDashboard = $liveStream->classroom_dashboard ?? 'jitsi';
        $jitsiRoom      = 'eduvera-' . $liveStream->id . '-' . Str::slug($liveStream->title, '-');

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

        $secondsUntilEnd = $liveStream->end_datetime
            ? (int) max(0, now()->diffInSeconds($liveStream->end_datetime, false))
            : null;

        // Use Unix timestamps — completely timezone-safe
        $elapsedSeconds = $liveStream->start_datetime
            ? (int) max(0, now()->timestamp - $liveStream->start_datetime->timestamp)
            : 0;

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
            'routePrefix'    => 'teacher',
            'watermark'      => (function () {
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

    public function updateStatus(Request $request, LiveStream $liveStream)
    {
        $this->authorize($liveStream);

        $request->validate(['status' => 'required|in:scheduled,live,ended']);

        $updateData = ['status' => $request->status];
        if ($request->status === 'ended') {
            $updateData['end_datetime'] = now();
        }

        $liveStream->update($updateData);

        return back()->with('success', match ($request->status) {
            'live'      => 'بدأ البث المباشر.',
            'ended'     => 'تم إنهاء البث.',
            'scheduled' => 'تم إعادة الجدولة.',
        });
    }

    public function uploadRecording(Request $request, LiveStream $liveStream)
    {
        $this->authorize($liveStream);

        $request->validate([
            'recording' => 'required|file|max:2097152',
        ]);

        $file   = $request->file('recording');
        $sizeMb = round($file->getSize() / 1024 / 1024, 3);
        $sizeGb = $sizeMb / 1024;

        // Deduct from StorageWallet (track usage; never block upload)
        $wallet = StorageWallet::first();

        // Delete old recording file if any
        if ($liveStream->recording_path && Storage::disk('local')->exists($liveStream->recording_path)) {
            Storage::disk('local')->delete($liveStream->recording_path);
        }

        $path = $file->storeAs('recordings', $liveStream->id . '-' . now()->timestamp . '.webm', 'local');

        if ($wallet) {
            $wallet->deduct($sizeGb, 'تسجيل حصة: ' . $liveStream->title, $liveStream);
        }

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
        $this->authorize($liveStream);

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
        $this->authorize($liveStream);

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

    public function requestExtension(Request $request, LiveStream $liveStream)
    {
        $this->authorize($liveStream);
        $request->validate(['minutes' => 'required|in:10,15,20,25,30']);

        $minutes = (int) $request->minutes;

        $liveStream->update([
            'end_datetime' => Carbon::parse($liveStream->end_datetime)->addMinutes($minutes),
        ]);

        return response()->json([
            'success'       => true,
            'added_seconds' => $minutes * 60,
            'message'       => "تم تمديد البث بـ {$minutes} دقيقة.",
        ]);
    }

    public function remainingSeconds(LiveStream $liveStream)
    {
        $this->authorize($liveStream);

        $total = ($liveStream->start_datetime && $liveStream->end_datetime)
            ? (int) abs($liveStream->start_datetime->diffInSeconds($liveStream->end_datetime))
            : null;

        return response()->json(['seconds_until_end' => $total]);
    }
}
