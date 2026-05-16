<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Exception;

class ZoomService
{
    private string $accountId;
    private string $clientId;
    private string $clientSecret;
    private string $hostEmail;

    public function __construct()
    {
        $this->accountId    = $this->getSetting('zoom_account_id', '');
        $this->clientId     = $this->getSetting('zoom_client_id', '');
        $this->clientSecret = $this->getSetting('zoom_client_secret', '');
        $this->hostEmail    = $this->getSetting('zoom_host_email', '');
    }

    private function getSetting(string $key, string $default = ''): string
    {
        $setting = Setting::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public function isConfigured(): bool
    {
        return !empty($this->accountId)
            && !empty($this->clientId)
            && !empty($this->clientSecret)
            && !empty($this->hostEmail);
    }

    /**
     * Get an OAuth2 access token using Server-to-Server OAuth.
     */
    public function getAccessToken(): string
    {
        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->post('https://zoom.us/oauth/token', [
                'grant_type' => 'account_credentials',
                'account_id' => $this->accountId,
            ]);

        if (!$response->successful()) {
            throw new Exception('Failed to get Zoom access token: ' . $response->body());
        }

        return $response->json('access_token');
    }

    /**
     * Create a Zoom meeting.
     *
     * @param string $topic        Meeting title
     * @param string $startDateTime ISO 8601 format (e.g. 2026-03-29T10:00:00)
     * @param int    $duration      Duration in minutes
     * @return array{meetingId: string, joinUrl: string}
     */
    public function createMeeting(string $topic, string $startDateTime, int $duration = 60): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->post("https://api.zoom.us/v2/users/{$this->hostEmail}/meetings", [
                'topic'      => $topic,
                'type'       => 2, // Scheduled meeting
                'start_time' => Carbon::parse($startDateTime)->toIso8601String(),
                'duration'   => $duration,
                'settings'   => [
                    'host_video'        => true,
                    'participant_video'  => true,
                    'join_before_host'  => false,
                    'waiting_room'      => true,
                    'auto_recording'    => 'none',
                ],
            ]);

        if (!$response->successful()) {
            throw new Exception('Failed to create Zoom meeting: ' . $response->body());
        }

        $data = $response->json();

        return [
            'meetingId' => (string) $data['id'],
            'joinUrl'   => $data['join_url'],
        ];
    }

    /**
     * Sync attendance records from a Zoom meeting participants report.
     * Note: Available after the meeting ends; requires Zoom Pro/Business.
     *
     * @param string $meetingId
     * @return array
     */
    public function syncAttendance(string $meetingId): array
    {
        $token = $this->getAccessToken();

        $attendees  = [];
        $nextPageToken = '';

        do {
            $params = ['page_size' => 300];
            if ($nextPageToken) {
                $params['next_page_token'] = $nextPageToken;
            }

            $response = Http::withToken($token)
                ->get("https://api.zoom.us/v2/report/meetings/{$meetingId}/participants", $params);

            if (!$response->successful()) {
                throw new Exception('Failed to get Zoom attendance: ' . $response->body());
            }

            $data         = $response->json();
            $participants = $data['participants'] ?? [];

            foreach ($participants as $participant) {
                $joinTime  = $participant['join_time'] ?? null;
                $leaveTime = $participant['leave_time'] ?? null;
                $duration  = $participant['duration'] ?? 0;

                $attendees[] = [
                    'student_name'     => $participant['name'] ?? 'Unknown',
                    'student_email'    => $participant['user_email'] ?? null,
                    'join_time'        => $joinTime,
                    'leave_time'       => $leaveTime,
                    'duration_seconds' => $duration,
                ];
            }

            $nextPageToken = $data['next_page_token'] ?? '';
        } while (!empty($nextPageToken));

        return $attendees;
    }

    /**
     * Delete a Zoom meeting.
     */
    public function deleteMeeting(string $meetingId): bool
    {
        try {
            $token = $this->getAccessToken();

            Http::withToken($token)
                ->delete("https://api.zoom.us/v2/meetings/{$meetingId}");

            return true;
        } catch (Exception) {
            return false;
        }
    }
}
