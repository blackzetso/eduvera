<?php

namespace App\Services;

use ToshY\BunnyNet\BunnyHttpClient;
use ToshY\BunnyNet\Enum\Endpoint;
use Symfony\Component\HttpClient\Psr18Client;
use Psr\Http\Client\ClientInterface;

class BunnyService
{
    protected $client;
    protected $libraryId;
    protected $apiKey;

    public function __construct(?string $libraryId = null, ?string $apiKey = null)
    {
        // Use tenant-specific library if provided, otherwise use default
        $this->libraryId = $libraryId ?: config('services.bunny.stream_library_id');
        $this->apiKey = $apiKey ?: config('services.bunny.stream_api_key') ?: config('services.bunny.api_key');

        $this->client = new BunnyHttpClient(
            client: new Psr18Client(),
            apiKey: $this->apiKey,
            baseUrl: Endpoint::STREAM,
        );
    }

    public function client()
    {
        return $this->client;
    }

    public function getLibraryId(): string
    {
        return $this->libraryId;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * Create a BunnyService instance with default configuration
     */
    public static function make(): self
    {
        return new self();
    }
}
