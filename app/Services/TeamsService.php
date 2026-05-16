<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Exception;

class TeamsService
{
    private string $tenantId;
    private string $clientId;
    private string $clientSecret;
    private string $serviceAccountEmail;

    public function __construct()
    {
        $this->tenantId           = $this->getSetting('teams_tenant_id', '');
        $this->clientId           = $this->getSetting('teams_client_id', '');
        $this->clientSecret       = $this->getSetting('teams_client_secret', '');
        $this->serviceAccountEmail = $this->getSetting('teams_service_account_email', '');
    }

    private function getSetting(string $key, string $default = ''): string
    {
        $setting = Setting::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public function isConfigured(): bool
    {
        return !empty($this->tenantId)
            && !empty($this->clientId)
            && !empty($this->clientSecret)
            && !empty($this->serviceAccountEmail);
    }

    /**
     * Get an OAuth2 access token using client credentials flow.
     */
    public function getAccessToken(): string
    {
        $response = Http::asForm()->post(
            "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token",
            [
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope'         => 'https://graph.microsoft.com/.default',
            ]
        );

        if (!$response->successful()) {
            throw new Exception('Failed to get Teams access token: ' . $response->body());
        }

        return $response->json('access_token');
    }

    /**
     * Get the Microsoft 365 user ID for the service account.
     */
    private function getServiceUserId(string $token): string
    {
        $response = Http::withToken($token)
            ->get("https://graph.microsoft.com/v1.0/users/{$this->serviceAccountEmail}");

        if (!$response->successful()) {
            throw new Exception('Failed to get service account user: ' . $response->body());
        }

        return $response->json('id');
    }

    /**
     * Create a Teams online meeting.
     *
     * @param string $subject
     * @param string $startDateTime ISO 8601 format
     * @param string $endDateTime   ISO 8601 format
     * @return array{meetingId: string, joinUrl: string}
     */
    public function createMeeting(string $subject, string $startDateTime, string $endDateTime): array
    {
        $token  = $this->getAccessToken();
        $userId = $this->getServiceUserId($token);

        $response = Http::withToken($token)
            ->post("https://graph.microsoft.com/v1.0/users/{$userId}/onlineMeetings", [
                'subject'       => $subject,
                'startDateTime' => $startDateTime,
                'endDateTime'   => $endDateTime,
            ]);

        if (!$response->successful()) {
            throw new Exception('Failed to create Teams meeting: ' . $response->body());
        }

        $data = $response->json();

        return [
            'meetingId' => $data['id'],
            'joinUrl'   => $data['joinWebUrl'],
        ];
    }

    /**
     * Sync attendance records from a Teams meeting attendance report.
     *
     * @param string $meetingId  The Teams meeting ID stored in our DB
     * @return array  Array of attendance records
     */
    public function syncAttendance(string $meetingId): array
    {
        $token  = $this->getAccessToken();
        $userId = $this->getServiceUserId($token);

        // Get attendance reports for this meeting
        $reportsResponse = Http::withToken($token)
            ->get("https://graph.microsoft.com/v1.0/users/{$userId}/onlineMeetings/{$meetingId}/attendanceReports");

        if (!$reportsResponse->successful()) {
            throw new Exception('Failed to get attendance reports: ' . $reportsResponse->body());
        }

        $reports = $reportsResponse->json('value', []);

        if (empty($reports)) {
            return [];
        }

        // Use the most recent report
        $latestReport   = $reports[0];
        $attendanceReportId = $latestReport['id'];

        // Get attendance records for this report
        $recordsResponse = Http::withToken($token)
            ->get("https://graph.microsoft.com/v1.0/users/{$userId}/onlineMeetings/{$meetingId}/attendanceReports/{$attendanceReportId}/attendanceRecords");

        if (!$recordsResponse->successful()) {
            throw new Exception('Failed to get attendance records: ' . $recordsResponse->body());
        }

        $records = $recordsResponse->json('value', []);

        $attendees = [];
        foreach ($records as $record) {
            $attendanceIntervals = $record['attendanceIntervals'] ?? [];
            $totalDuration = 0;
            $firstJoin = null;
            $lastLeave = null;

            foreach ($attendanceIntervals as $interval) {
                $joinTime  = $interval['joinDateTime'] ?? null;
                $leaveTime = $interval['leaveDateTime'] ?? null;
                $duration  = $interval['durationInSeconds'] ?? 0;
                $totalDuration += $duration;

                if ($joinTime && (!$firstJoin || $joinTime < $firstJoin)) {
                    $firstJoin = $joinTime;
                }
                if ($leaveTime && (!$lastLeave || $leaveTime > $lastLeave)) {
                    $lastLeave = $leaveTime;
                }
            }

            $identity = $record['identity'] ?? [];

            $attendees[] = [
                'student_name'     => $identity['displayName'] ?? ($record['emailAddress'] ?? 'Unknown'),
                'student_email'    => $identity['tenantId'] ? ($record['emailAddress'] ?? null) : null,
                'join_time'        => $firstJoin,
                'leave_time'       => $lastLeave,
                'duration_seconds' => $totalDuration,
            ];
        }

        return $attendees;
    }

    /**
     * Delete a Teams meeting.
     */
    public function deleteMeeting(string $meetingId): bool
    {
        try {
            $token  = $this->getAccessToken();
            $userId = $this->getServiceUserId($token);

            Http::withToken($token)
                ->delete("https://graph.microsoft.com/v1.0/users/{$userId}/onlineMeetings/{$meetingId}");

            return true;
        } catch (Exception) {
            return false;
        }
    }
}
