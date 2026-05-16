<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ExchangeRateService
{
    protected string $apiUrl;
    protected string $apiKey;
    protected int $cacheDuration = 3600; // 1 hour in seconds

    public function __construct()
    {
        $this->apiUrl = config('services.exchange_rate.api_url');
        $this->apiKey = config('services.exchange_rate.api_key', '');
    }

    /**
     * Fetch exchange rate from API
     */
    public function fetchRate(string $from = 'USD', string $to = 'EGP'): ?float
    {
        try {
            Log::info('Fetching exchange rate from API', [
                'from' => $from,
                'to' => $to,
            ]);

            // Build API URL
            // Using exchangerate-api.com free tier
            $url = str_replace('{from}', $from, $this->apiUrl);
            
            $response = Http::timeout(10)->get($url);

            if (!$response->successful()) {
                Log::error('Exchange rate API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();

            // Check if response is valid
            // API can return either 'conversion_rates' or 'rates'
            $rates = $data['conversion_rates'] ?? $data['rates'] ?? null;
            
            if (!$rates || !isset($rates[$to])) {
                Log::error('Exchange rate not found in API response', [
                    'response' => $data,
                    'to_currency' => $to,
                ]);
                return null;
            }

            $rate = (float) $rates[$to];

            Log::info('Exchange rate fetched successfully', [
                'from' => $from,
                'to' => $to,
                'rate' => $rate,
            ]);

            return $rate;
        } catch (\Exception $e) {
            Log::error('Exception fetching exchange rate: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Update exchange rate in database
     */
    public function updateRate(string $from = 'USD', string $to = 'EGP'): bool
    {
        try {
            $rate = $this->fetchRate($from, $to);

            if ($rate === null) {
                Log::warning('Could not fetch exchange rate, keeping existing rate');
                return false;
            }

            // Update in database
            ExchangeRate::updateRate($from, $to, $rate, 'exchangerate-api.com');

            // Clear cache
            Cache::forget("exchange_rate_{$from}_{$to}");

            Log::info('Exchange rate updated successfully', [
                'from' => $from,
                'to' => $to,
                'rate' => $rate,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error updating exchange rate: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get current exchange rate (from cache or database)
     */
    public function getRate(string $from = 'USD', string $to = 'EGP'): float
    {
        $cacheKey = "exchange_rate_{$from}_{$to}";

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($from, $to) {
            // Try to get from database
            $rate = ExchangeRate::getActiveRate($from, $to);

            if ($rate !== null) {
                return $rate;
            }

            // If not in database, fetch from API
            $fetchedRate = $this->fetchRate($from, $to);

            if ($fetchedRate !== null) {
                // Save to database
                ExchangeRate::updateRate($from, $to, $fetchedRate, 'exchangerate-api.com');
                return $fetchedRate;
            }

            // Fallback to constant value
            Log::warning('Using fallback exchange rate');
            return PricingService::USD_TO_EGP_RATE;
        });
    }

    /**
     * Check if API is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiUrl);
    }

    /**
     * Get configuration status
     */
    public function getConfigurationStatus(): array
    {
        return [
            'is_configured' => $this->isConfigured(),
            'api_url' => $this->apiUrl,
            'has_api_key' => !empty($this->apiKey),
        ];
    }

    /**
     * Test API connection
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Exchange Rate API غير مُعد',
            ];
        }

        try {
            $rate = $this->fetchRate('USD', 'EGP');

            if ($rate !== null) {
                return [
                    'success' => true,
                    'message' => 'الاتصال بـ Exchange Rate API ناجح',
                    'rate' => $rate,
                ];
            }

            return [
                'success' => false,
                'message' => 'فشل في الحصول على سعر الصرف من API',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'خطأ في الاتصال: ' . $e->getMessage(),
            ];
        }
    }
}

