<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BunnyLibraryService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.bunny.net';

    public function __construct()
    {
        $this->apiKey = config('services.bunny.api_key');
    }

    /**
     * Create a new Bunny Stream library
     */
    public function createLibrary(string $name, array $replicationRegions = ['DE', 'NY']): ?array
    {
        try {
            $response = Http::withHeaders([
                'AccessKey' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/videolibrary", [
                'Name' => $name,
                'ReplicationRegions' => $replicationRegions,
            ]);

            if ($response->successful()) {
                $libraryData = $response->json();
                
                Log::info("Created Bunny library", [
                    'name' => $name,
                    'library_id' => $libraryData['Id'],
                ]);

                return $libraryData;
            }

            Log::error("Failed to create Bunny library", [
                'name' => $name,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error("Exception creating Bunny library: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get library details (includes storage and bandwidth totals)
     */
    public function getLibraryDetails(string $libraryId): ?array
    {
        try {
            // استخدام الـ general API key بدلاً من stream API key
            $generalApiKey = config('services.bunny.api_key');
            
            if (!$generalApiKey) {
                Log::error("Bunny general API key not configured");
                return null;
            }
            
            $response = Http::withHeaders([
                'AccessKey' => $generalApiKey,
            ])->get("https://api.bunny.net/videolibrary/{$libraryId}");

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('Bunny library details', [
                    'storage_usage' => $data['StorageUsage'] ?? 0,
                    'traffic_usage' => $data['TrafficUsage'] ?? 0,
                    'video_count' => $data['VideoCount'] ?? 0,
                ]);
                
                return $data;
            }

            Log::error("Failed to get Bunny library details", [
                'library_id' => $libraryId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error("Exception getting Bunny library details: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get library statistics from Bunny
     */
    public function getLibraryStatistics(string $libraryId, string $apiKey): ?array
    {
        try {
            $response = Http::withHeaders([
                'AccessKey' => $apiKey,
            ])->get("https://video.bunnycdn.com/library/{$libraryId}/statistics");

            if ($response->successful()) {
                return $response->json();
            }

            Log::error("Failed to get Bunny library statistics", [
                'library_id' => $libraryId,
                'status' => $response->status(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error("Exception getting Bunny library statistics: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get detailed video statistics
     */
    public function getVideoStatistics(string $libraryId, string $videoId, string $apiKey): ?array
    {
        try {
            $response = Http::withHeaders([
                'AccessKey' => $apiKey,
            ])->get("https://video.bunnycdn.com/library/{$libraryId}/videos/{$videoId}");

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Exception getting video statistics: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Sync consumption data from Bunny
     */
    public function syncConsumption(?string $libraryId = null, ?string $apiKey = null): array
    {
        $libraryId = $libraryId ?: config('services.bunny.stream_library_id');
        $apiKey = $apiKey ?: config('services.bunny.stream_api_key');

        if (!$libraryId || !$apiKey) {
            return ['success' => false, 'message' => 'Bunny library not configured'];
        }

        // استخدام endpoint الصحيح
        $libraryDetails = $this->getLibraryDetails($libraryId);

        if (!$libraryDetails) {
            return ['success' => false, 'message' => 'Failed to fetch library details'];
        }

        // البيانات الصحيحة من libraryDetails
        $storageUsed = $libraryDetails['StorageUsage'] ?? 0; // in bytes
        $bandwidthUsed = $libraryDetails['TrafficUsage'] ?? 0; // in bytes

        $storageGB = $storageUsed / (1024 ** 3);
        $bandwidthGB = $bandwidthUsed / (1024 ** 3);

        Log::info('Bunny sync consumption', [
            'storage_gb' => $storageGB,
            'bandwidth_gb' => $bandwidthGB,
            'storage_bytes' => $storageUsed,
            'bandwidth_bytes' => $bandwidthUsed,
        ]);

        return [
            'success' => true,
            'storage_gb' => round($storageGB, 4),
            'bandwidth_gb' => round($bandwidthGB, 4),
            'storage_bytes' => $storageUsed,
            'bandwidth_bytes' => $bandwidthUsed,
        ];
    }

    /**
     * Delete a video from Bunny
     */
    public function deleteVideo(string $libraryId, string $videoId, string $apiKey): bool
    {
        try {
            $response = Http::withHeaders([
                'AccessKey' => $apiKey,
            ])->delete("https://video.bunnycdn.com/library/{$libraryId}/videos/{$videoId}");

            return $response->successful() || $response->status() === 404;
        } catch (\Exception $e) {
            Log::error("Exception deleting video from Bunny: " . $e->getMessage());
            return false;
        }
    }
}

