<?php

namespace App\Services;

use App\Models\Setting;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Exception;

class GoogleMeetService
{
    private const TOKEN_URL  = 'https://oauth2.googleapis.com/token';
    private const MEET_API   = 'https://meet.googleapis.com/v2';
    private const SCOPES     = 'https://www.googleapis.com/auth/meetings.space.created https://www.googleapis.com/auth/meetings.space.readonly';

    private string $clientEmail;
    private string $privateKey;
    private string $impersonateEmail;

    public function __construct()
    {
        $this->clientEmail      = $this->getSetting('google_meet_client_email', '');
        $this->privateKey       = $this->getSetting('google_meet_private_key', '');
        $this->impersonateEmail = $this->getSetting('google_meet_impersonate_email', '');
    }

    private function getSetting(string $key, string $default = ''): string
    {
        $setting = Setting::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public function isConfigured(): bool
    {
        return !empty($this->clientEmail)
            && !empty($this->privateKey)
            && !empty($this->impersonateEmail);
    }

    /**
     * Exchange a signed JWT for a Google OAuth2 access token.
     * Uses Service Account with domain-wide delegation.
     */
    private function getAccessToken(): string
    {
        $now = time();

        $jwtPayload = [
            'iss'   => $this->clientEmail,
            'sub'   => $this->impersonateEmail,
            'scope' => self::SCOPES,
            'aud'   => self::TOKEN_URL,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];

        // Normalize private key: replace literal \n with actual newlines
        $privateKey = str_replace('\\n', "\n", $this->privateKey);

        $jwtAssertion = JWT::encode($jwtPayload, $privateKey, 'RS256');

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwtAssertion,
        ]);

        if (!$response->successful()) {
            throw new Exception('Failed to get Google OAuth token: ' . $response->body());
        }

        return $response->json('access_token');
    }

    /**
     * Create a Google Meet space.
     *
     * @return array{spaceName: string, meetingCode: string, joinUrl: string}
     */
    public function createSpace(): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post(self::MEET_API . '/spaces', []);

        if (!$response->successful()) {
            throw new Exception('Failed to create Google Meet space: ' . $response->body());
        }

        $data = $response->json();

        return [
            'spaceName'   => $data['name']        ?? '',          // e.g. "spaces/abc123"
            'meetingCode' => $data['meetingCode']  ?? '',          // e.g. "abc-defg-hij"
            'joinUrl'     => $data['meetingUri']   ?? '',          // https://meet.google.com/...
        ];
    }

    /**
     * Fetch attendance records from Google Meet conference records.
     * Uses the Meet REST API v2 to get participants and their sessions.
     *
     * @param string $spaceName  e.g. "spaces/abc123"
     * @return array  Array of attendance records
     */
    public function syncAttendance(string $spaceName): array
    {
        $token = $this->getAccessToken();

        // Step 1 – find conference records for this space
        $confResponse = Http::withToken($token)
            ->get(self::MEET_API . '/conferenceRecords', [
                'filter' => "space.name=\"{$spaceName}\"",
            ]);

        if (!$confResponse->successful()) {
            throw new Exception('Failed to fetch conference records: ' . $confResponse->body());
        }

        $conferenceRecords = $confResponse->json('conferenceRecords') ?? [];

        if (empty($conferenceRecords)) {
            return [];
        }

        $attendees = [];

        foreach ($conferenceRecords as $record) {
            $recordName = $record['name']; // e.g. "conferenceRecords/abc123"

            // Step 2 – list participants
            $participantsResponse = Http::withToken($token)
                ->get(self::MEET_API . "/{$recordName}/participants");

            if (!$participantsResponse->successful()) {
                continue;
            }

            $participants = $participantsResponse->json('participants') ?? [];

            foreach ($participants as $participant) {
                $participantName = $participant['name']; // "conferenceRecords/.../participants/..."

                // Determine display name and email
                $displayName  = $participant['signedinUser']['displayName']  ?? null;
                $email        = $participant['signedinUser']['email']         ?? null;
                $anonymousUser = $participant['anonymousUser']['displayName'] ?? null;
                $name = $displayName ?? $anonymousUser ?? 'Unknown';

                // Step 3 – list participant sessions to get join/leave times
                $sessionsResponse = Http::withToken($token)
                    ->get(self::MEET_API . "/{$participantName}/participantSessions");

                $sessions = $sessionsResponse->successful()
                    ? ($sessionsResponse->json('participantSessions') ?? [])
                    : [];

                if (empty($sessions)) {
                    $attendees[] = [
                        'student_name'     => $name,
                        'student_email'    => $email,
                        'join_time'        => null,
                        'leave_time'       => null,
                        'duration_seconds' => 0,
                    ];
                    continue;
                }

                foreach ($sessions as $session) {
                    $joinTime  = $session['startTime']  ?? null;
                    $leaveTime = $session['endTime']    ?? null;

                    $durationSeconds = 0;
                    if ($joinTime && $leaveTime) {
                        $durationSeconds = strtotime($leaveTime) - strtotime($joinTime);
                    } elseif ($joinTime) {
                        $durationSeconds = time() - strtotime($joinTime);
                    }

                    $attendees[] = [
                        'student_name'     => $name,
                        'student_email'    => $email,
                        'join_time'        => $joinTime  ? date('Y-m-d H:i:s', strtotime($joinTime))  : null,
                        'leave_time'       => $leaveTime ? date('Y-m-d H:i:s', strtotime($leaveTime)) : null,
                        'duration_seconds' => max(0, $durationSeconds),
                    ];
                }
            }
        }

        return $attendees;
    }

    /**
     * End / close a Google Meet space (marks it as no longer accepting new participants).
     * Note: Google Meet does not have a hard "delete" API for spaces.
     */
    public function endSpace(string $spaceName): bool
    {
        try {
            $token = $this->getAccessToken();

            Http::withToken($token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->patch(self::MEET_API . "/{$spaceName}:endActiveConference", []);

            return true;
        } catch (Exception) {
            return false;
        }
    }
}
