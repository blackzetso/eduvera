<?php

namespace App\Services;

use App\Models\Setting;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Exception;

class HMSService
{
    private const API_BASE = 'https://api.100ms.live/v2';

    private string $appAccessKey;
    private string $appSecret;
    private string $templateId;

    public function __construct()
    {
        $this->appAccessKey = $this->getSetting('hms_app_access_key', '');
        $this->appSecret    = $this->getSetting('hms_app_secret', '');
        $this->templateId   = $this->getSetting('hms_template_id', '');
    }

    private function getSetting(string $key, string $default = ''): string
    {
        $setting = Setting::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public function isConfigured(): bool
    {
        return !empty($this->appAccessKey)
            && !empty($this->appSecret)
            && !empty($this->templateId);
    }

    /**
     * Generate a management token for 100ms REST API calls.
     * Never expose this token to the frontend.
     */
    public function generateManagementToken(): string
    {
        $now = time();

        $payload = [
            'access_key' => $this->appAccessKey,
            'type'       => 'management',
            'version'    => 2,
            'jti'        => (string) Str::uuid(),
            'iat'        => $now,
            'nbf'        => $now,
            'exp'        => $now + 86400,
        ];

        return JWT::encode($payload, $this->appSecret, 'HS256');
    }

    /**
     * Generate an auth token for a room participant (teacher or student).
     * This token is safe to pass to the frontend.
     *
     * @param string $roomId   100ms room ID
     * @param string $userId   Internal user identifier
     * @param string $role     'teacher' or 'student'
     * @param string $userName Display name shown in the room
     */
    public function generateAuthToken(
        string $roomId,
        string $userId,
        string $role,
        string $userName
    ): string {
        $now = time();

        $payload = [
            'access_key' => $this->appAccessKey,
            'type'       => 'app',
            'version'    => 2,
            'room_id'    => $roomId,
            'user_id'    => $userId,
            'role'       => $role,
            'jti'        => (string) Str::uuid(),
            'iat'        => $now,
            'nbf'        => $now,
            'exp'        => $now + 86400,
        ];

        return JWT::encode($payload, $this->appSecret, 'HS256');
    }

    /**
     * Create a 100ms room.
     *
     * @param string $name  Unique room name (slugified stream title + id)
     * @return array{roomId: string, name: string}
     */
    public function createRoom(string $name): array
    {
        $managementToken = $this->generateManagementToken();

        $response = Http::withToken($managementToken)
            ->post(self::API_BASE . '/rooms', [
                'name'        => $name,
                'template_id' => $this->templateId,
            ]);

        if (!$response->successful()) {
            // If room already exists 100ms returns 409 — fetch it by name
            if ($response->status() === 409) {
                return $this->getRoomByName($name);
            }
            throw new Exception('Failed to create 100ms room: ' . $response->body());
        }

        $data = $response->json();

        return [
            'roomId' => $data['id'],
            'name'   => $data['name'],
        ];
    }

    /**
     * Fetch an existing room by name.
     */
    private function getRoomByName(string $name): array
    {
        $managementToken = $this->generateManagementToken();

        $response = Http::withToken($managementToken)
            ->get(self::API_BASE . '/rooms', ['name' => $name]);

        if (!$response->successful()) {
            throw new Exception('Failed to fetch 100ms room: ' . $response->body());
        }

        $rooms = $response->json('data') ?? [];

        if (empty($rooms)) {
            throw new Exception("100ms room '{$name}' not found.");
        }

        return [
            'roomId' => $rooms[0]['id'],
            'name'   => $rooms[0]['name'],
        ];
    }

    /**
     * End an active session in a room (kicks all participants).
     */
    public function endRoom(string $roomId, string $reason = 'Stream ended'): bool
    {
        try {
            $managementToken = $this->generateManagementToken();

            Http::withToken($managementToken)
                ->post(self::API_BASE . "/sessions/{$roomId}/end", [
                    'reason' => $reason,
                    'lock'   => false,
                ]);

            return true;
        } catch (Exception) {
            return false;
        }
    }
}
