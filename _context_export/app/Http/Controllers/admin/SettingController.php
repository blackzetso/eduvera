<?php

namespace App\Http\Controllers\admin;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use Inertia\Inertia;

class SettingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('Admin/theme1/Settings/Index');
    }

    public function teamsSettings()
    {
        $keys = ['teams_tenant_id', 'teams_client_id', 'teams_client_secret', 'teams_service_account_email'];

        $settings = [];
        foreach ($keys as $key) {
            $setting = Setting::where('key', $key)->first();
            $settings[$key] = $setting ? $setting->value : '';
        }

        // Mask the client secret for display
        if (!empty($settings['teams_client_secret'])) {
            $settings['teams_client_secret_masked'] = str_repeat('*', 8) . substr($settings['teams_client_secret'], -4);
        } else {
            $settings['teams_client_secret_masked'] = '';
        }

        return Inertia::render('Admin/theme1/Settings/TeamsSettings', [
            'settings' => $settings,
        ]);
    }

    public function updateTeamsSettings(Request $request)
    {
        $request->validate([
            'teams_tenant_id'             => 'required|string|max:255',
            'teams_client_id'             => 'required|string|max:255',
            'teams_client_secret'         => 'nullable|string|max:500',
            'teams_service_account_email' => 'required|email|max:255',
        ]);

        $keys = [
            'teams_tenant_id',
            'teams_client_id',
            'teams_service_account_email',
        ];

        foreach ($keys as $key) {
            Setting::updateOrCreate(['key' => $key], ['value' => $request->input($key)]);
        }

        // Only update client secret if a new one is provided
        if ($request->filled('teams_client_secret')) {
            Setting::updateOrCreate(['key' => 'teams_client_secret'], ['value' => $request->input('teams_client_secret')]);
        }

        return back()->with('success', 'تم حفظ إعدادات Microsoft Teams بنجاح.');
    }

    public function zoomSettings()
    {
        $keys = ['zoom_account_id', 'zoom_client_id', 'zoom_client_secret', 'zoom_host_email'];

        $settings = [];
        foreach ($keys as $key) {
            $setting = Setting::where('key', $key)->first();
            $settings[$key] = $setting ? $setting->value : '';
        }

        if (!empty($settings['zoom_client_secret'])) {
            $settings['zoom_client_secret_masked'] = str_repeat('*', 8) . substr($settings['zoom_client_secret'], -4);
        } else {
            $settings['zoom_client_secret_masked'] = '';
        }

        return Inertia::render('Admin/theme1/Settings/ZoomSettings', [
            'settings' => $settings,
        ]);
    }

    public function updateZoomSettings(Request $request)
    {
        $request->validate([
            'zoom_account_id'    => 'required|string|max:255',
            'zoom_client_id'     => 'required|string|max:255',
            'zoom_client_secret' => 'nullable|string|max:500',
            'zoom_host_email'    => 'required|email|max:255',
        ]);

        $keys = ['zoom_account_id', 'zoom_client_id', 'zoom_host_email'];

        foreach ($keys as $key) {
            Setting::updateOrCreate(['key' => $key], ['value' => $request->input($key)]);
        }

        if ($request->filled('zoom_client_secret')) {
            Setting::updateOrCreate(['key' => 'zoom_client_secret'], ['value' => $request->input('zoom_client_secret')]);
        }

        return back()->with('success', 'تم حفظ إعدادات Zoom بنجاح.');
    }

    public function livekitSettings()
    {
        $keys = ['livekit_server_url', 'livekit_api_key', 'livekit_api_secret'];

        $settings = [];
        foreach ($keys as $key) {
            $setting = Setting::where('key', $key)->first();
            $settings[$key] = $setting ? $setting->value : '';
        }

        if (!empty($settings['livekit_api_secret'])) {
            $settings['livekit_api_secret_masked'] = str_repeat('*', 8) . substr($settings['livekit_api_secret'], -4);
        } else {
            $settings['livekit_api_secret_masked'] = '';
        }

        return Inertia::render('Admin/theme1/Settings/LiveKitSettings', [
            'settings' => $settings,
        ]);
    }

    public function updateLivekitSettings(Request $request)
    {
        $request->validate([
            'livekit_server_url' => 'required|url|max:255',
            'livekit_api_key'    => 'required|string|max:255',
            'livekit_api_secret' => 'nullable|string|max:500',
        ]);

        Setting::updateOrCreate(['key' => 'livekit_server_url'], ['value' => $request->input('livekit_server_url')]);
        Setting::updateOrCreate(['key' => 'livekit_api_key'],    ['value' => $request->input('livekit_api_key')]);

        if ($request->filled('livekit_api_secret')) {
            Setting::updateOrCreate(['key' => 'livekit_api_secret'], ['value' => $request->input('livekit_api_secret')]);
        }

        return back()->with('success', 'تم حفظ إعدادات LiveKit بنجاح.');
    }

    public function googleMeetSettings()
    {
        $keys = ['google_meet_client_email', 'google_meet_private_key', 'google_meet_impersonate_email'];

        $settings = [];
        foreach ($keys as $key) {
            $setting = Setting::where('key', $key)->first();
            $settings[$key] = $setting ? $setting->value : '';
        }

        // Mask private key for display
        if (!empty($settings['google_meet_private_key'])) {
            $settings['google_meet_private_key_masked'] = '-----BEGIN PRIVATE KEY-----  (محفوظ)';
        } else {
            $settings['google_meet_private_key_masked'] = '';
        }

        return Inertia::render('Admin/theme1/Settings/GoogleMeetSettings', [
            'settings' => $settings,
        ]);
    }

    public function updateGoogleMeetSettings(Request $request)
    {
        $request->validate([
            'google_meet_client_email'     => 'required|email|max:255',
            'google_meet_private_key'      => 'nullable|string',
            'google_meet_impersonate_email'=> 'required|email|max:255',
        ]);

        Setting::updateOrCreate(['key' => 'google_meet_client_email'],      ['value' => $request->input('google_meet_client_email')]);
        Setting::updateOrCreate(['key' => 'google_meet_impersonate_email'], ['value' => $request->input('google_meet_impersonate_email')]);

        if ($request->filled('google_meet_private_key')) {
            Setting::updateOrCreate(['key' => 'google_meet_private_key'], ['value' => $request->input('google_meet_private_key')]);
        }

        return back()->with('success', 'تم حفظ إعدادات Google Meet بنجاح.');
    }

    public function hmsSettings()
    {
        $keys = ['hms_app_access_key', 'hms_app_secret', 'hms_template_id'];

        $settings = [];
        foreach ($keys as $key) {
            $setting = Setting::where('key', $key)->first();
            $settings[$key] = $setting ? $setting->value : '';
        }

        if (!empty($settings['hms_app_secret'])) {
            $settings['hms_app_secret_masked'] = str_repeat('*', 8) . substr($settings['hms_app_secret'], -4);
        } else {
            $settings['hms_app_secret_masked'] = '';
        }

        return Inertia::render('Admin/theme1/Settings/HMSSettings', [
            'settings' => $settings,
        ]);
    }

    public function updateHmsSettings(Request $request)
    {
        $request->validate([
            'hms_app_access_key' => 'required|string|max:255',
            'hms_app_secret'     => 'nullable|string|max:500',
            'hms_template_id'    => 'required|string|max:255',
        ]);

        Setting::updateOrCreate(['key' => 'hms_app_access_key'], ['value' => $request->input('hms_app_access_key')]);
        Setting::updateOrCreate(['key' => 'hms_template_id'],    ['value' => $request->input('hms_template_id')]);

        if ($request->filled('hms_app_secret')) {
            Setting::updateOrCreate(['key' => 'hms_app_secret'], ['value' => $request->input('hms_app_secret')]);
        }

        return back()->with('success', 'تم حفظ إعدادات 100ms بنجاح.');
    }

    public function liveStreamDetails()
    {
        $setting = Setting::where('key', 'live_stream_max_duration')->first();
        $maxDuration = $setting ? (int) $setting->value : 60;

        $pendingRequests = \App\Models\LiveStream::where('extra_session_status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($s) => [
                'id'             => $s->id,
                'title'          => $s->title,
                'teacher_name'   => $s->teacher_name,
                'teacher_email'  => $s->teacher_email,
                'subject'        => $s->subject,
                'start_datetime' => $s->start_datetime?->format('Y-m-d H:i'),
                'end_datetime'   => $s->end_datetime?->format('Y-m-d H:i'),
            ]);

        $pendingExtensions = \App\Models\LiveStream::whereNotNull('pending_extension_minutes')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn ($s) => [
                'id'               => $s->id,
                'title'            => $s->title,
                'teacher_name'     => $s->teacher_name,
                'subject'          => $s->subject,
                'start_datetime'   => $s->start_datetime?->format('Y-m-d H:i'),
                'extension_minutes'=> $s->pending_extension_minutes,
            ]);

        $wmSettings = Setting::whereIn('key', [
            'live_stream_watermark',
            'live_stream_watermark_position',
            'live_stream_watermark_opacity',
            'live_stream_watermark_size',
        ])->pluck('value', 'key');

        $wmPath    = $wmSettings->get('live_stream_watermark');
        $watermark = $wmPath ? [
            'url'      => asset('storage/' . $wmPath),
            'position' => $wmSettings->get('live_stream_watermark_position', 'bottom-right'),
            'opacity'  => (int) $wmSettings->get('live_stream_watermark_opacity', 20),
            'size'     => (int) $wmSettings->get('live_stream_watermark_size', 100),
        ] : null;

        return Inertia::render('Admin/theme1/LiveStreams/Settings', [
            'max_duration'       => $maxDuration,
            'pending_requests'   => $pendingRequests,
            'pending_extensions' => $pendingExtensions,
            'watermark'          => $watermark,
        ]);
    }

    public function updateLiveStreamDetails(Request $request)
    {
        $request->validate([
            'max_duration'       => 'nullable|integer|min:1|max:1440',
            'watermark'          => 'nullable|image|mimes:png,jpg,jpeg,gif,webp|max:2048',
            'watermark_position' => 'nullable|in:top-left,top-right,bottom-left,bottom-right,center',
            'watermark_opacity'  => 'nullable|integer|min:5|max:50',
            'watermark_size'     => 'nullable|integer|min:300|max:1000',
            'remove_watermark'   => 'nullable|boolean',
        ]);

        if ($request->filled('max_duration')) {
            Setting::updateOrCreate(
                ['key' => 'live_stream_max_duration'],
                ['value' => $request->input('max_duration')]
            );
        }

        if ($request->boolean('remove_watermark')) {
            $old = Setting::where('key', 'live_stream_watermark')->value('value');
            if ($old) Storage::disk('public')->delete($old);
            Setting::where('key', 'live_stream_watermark')->delete();
        } elseif ($request->hasFile('watermark')) {
            $old = Setting::where('key', 'live_stream_watermark')->value('value');
            if ($old) Storage::disk('public')->delete($old);
            $path = $request->file('watermark')->store('live-streams/watermarks', 'public');
            Setting::updateOrCreate(
                ['key' => 'live_stream_watermark'],
                ['value' => $path]
            );
        }

        if ($request->filled('watermark_position')) {
            Setting::updateOrCreate(
                ['key' => 'live_stream_watermark_position'],
                ['value' => $request->input('watermark_position')]
            );
        }

        if ($request->filled('watermark_opacity')) {
            Setting::updateOrCreate(
                ['key' => 'live_stream_watermark_opacity'],
                ['value' => $request->input('watermark_opacity')]
            );
        }

        if ($request->filled('watermark_size')) {
            Setting::updateOrCreate(
                ['key' => 'live_stream_watermark_size'],
                ['value' => $request->input('watermark_size')]
            );
        }

        return back()->with('success', 'تم حفظ الإعدادات بنجاح.');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Setting $setting)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Setting $setting)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Setting $setting)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Setting $setting)
    {
        //
    }
}
