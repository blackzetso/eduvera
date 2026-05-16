<?php

namespace App\Services;

use App\Models\Setting;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Exception;

class LiveKitService
{
    private string $serverUrl;
    private string $apiKey;
    private string $apiSecret;

    public function __construct()
    {
        $this->serverUrl = rtrim($this->getSetting('livekit_server_url', ''), '/');
        $this->apiKey    = $this->getSetting('livekit_api_key', '');
        $this->apiSecret = $this->getSetting('livekit_api_secret', '');
    }

    private function getSetting(string $key, string $default = ''): string
    {
        $setting = Setting::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public function isConfigured(): bool
    {
        return !empty($this->serverUrl)
            && !empty($this->apiKey)
            && !empty($this->apiSecret);
    }

    /**
     * Generate an admin JWT token for LiveKit REST API calls.
     * Uses roomCreate + roomList + roomAdmin grants for server-side operations.
     */
    private function generateAdminToken(): string
    {
        $now = time();

        $payload = [
            'iss' => $this->apiKey,
            'sub' => $this->apiKey,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 600, // 10 minutes
            'video' => [
                'roomCreate' => true,
                'roomList'   => true,
                'roomAdmin'  => true,
            ],
        ];

        return JWT::encode($payload, $this->apiSecret, 'HS256');
    }

    /**
     * Generate a participant join token for a specific room.
     *
     * @param string $roomName
     * @param string $participantName
     * @param bool   $canPublish  Whether participant can publish audio/video
     * @param int    $ttl         Token lifetime in seconds (default 24h)
     * @return string
     */
    public function generateJoinToken(
        string $roomName,
        string $participantName = 'participant',
        bool   $canPublish = true,
        int    $ttl = 86400
    ): string {
        $now = time();

        $payload = [
            'iss' => $this->apiKey,
            'sub' => $participantName . '-' . Str::random(6),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttl,
            'video' => [
                'roomJoin'      => true,
                'room'          => $roomName,
                'canPublish'    => $canPublish,
                'canSubscribe'  => true,
            ],
        ];

        return JWT::encode($payload, $this->apiSecret, 'HS256');
    }

    /**
     * Build a join URL using LiveKit Meet hosted UI.
     * Format: https://meet.livekit.io/?liveKitUrl=wss://server&token=JWT
     */
    public function buildJoinUrl(string $roomName, string $participantName = 'participant'): string
    {
        $token = $this->generateJoinToken($roomName, $participantName);

        // Convert http(s) to ws(s) for LiveKit URL
        $wsUrl = str_replace(['https://', 'http://'], ['wss://', 'ws://'], $this->serverUrl);

        return 'https://meet.livekit.io/?' . http_build_query([
            'liveKitUrl' => $wsUrl,
            'token'      => $token,
        ]);
    }

    /**
     * Create a LiveKit room via Twirp API.
     *
     * @param string $roomName  Unique room name
     * @param int    $emptyTimeout  Seconds before empty room is deleted (default 5 min)
     * @param int    $maxParticipants  Max allowed participants (0 = unlimited)
     * @return array{roomName: string, joinUrl: string}
     */
    public function createRoom(string $roomName, int $emptyTimeout = 300, int $maxParticipants = 0): array
    {
        $token = $this->generateAdminToken();

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("{$this->serverUrl}/twirp/livekit.RoomService/CreateRoom", [
                'name'             => $roomName,
                'empty_timeout'    => $emptyTimeout,
                'max_participants' => $maxParticipants,
            ]);

        if (!$response->successful()) {
            throw new Exception('Failed to create LiveKit room: ' . $response->body());
        }

        $joinUrl = $this->buildJoinUrl($roomName, 'teacher');

        return [
            'roomName' => $roomName,
            'joinUrl'  => $joinUrl,
        ];
    }

    /**
     * List current participants in a LiveKit room.
     * Note: Returns ACTIVE participants only. Historical data requires webhooks.
     *
     * @param string $roomName
     * @return array  Array of attendance-formatted records
     */
    public function listParticipants(string $roomName): array
    {
        $token = $this->generateAdminToken();

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("{$this->serverUrl}/twirp/livekit.RoomService/ListParticipants", [
                'room' => $roomName,
            ]);

        if (!$response->successful()) {
            throw new Exception('Failed to list LiveKit participants: ' . $response->body());
        }

        $data         = $response->json();
        $participants = $data['participants'] ?? [];

        $attendees = [];
        foreach ($participants as $p) {
            $joinedAt = isset($p['joined_at']) ? date('Y-m-d H:i:s', (int) $p['joined_at']) : null;

            $attendees[] = [
                'student_name'     => $p['name'] ?? $p['identity'] ?? 'Unknown',
                'student_email'    => null, // LiveKit does not expose emails
                'join_time'        => $joinedAt,
                'leave_time'       => null, // Active participants have no leave time yet
                'duration_seconds' => isset($p['joined_at']) ? (time() - (int) $p['joined_at']) : 0,
            ];
        }

        return $attendees;
    }

    /**
     * Delete a LiveKit room.
     */
    public function deleteRoom(string $roomName): bool
    {
        try {
            $token = $this->generateAdminToken();

            Http::withToken($token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->serverUrl}/twirp/livekit.RoomService/DeleteRoom", [
                    'room' => $roomName,
                ]);

            return true;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Generate a fresh teacher join URL (short-lived token for display).
     */
    public function getTeacherJoinUrl(string $roomName): string
    {
        return $this->buildJoinUrl($roomName, 'teacher');
    }

    /**
     * Generate a student join URL.
     */
    public function getStudentJoinUrl(string $roomName, string $studentName = 'student'): string
    {
        return $this->buildJoinUrl($roomName, $studentName);
    }
}
