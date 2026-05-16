<?php

namespace App\Services;

class PricingService
{
    // Bunny pricing (actual)
    const BUNNY_STORAGE_COST_PER_GB = 0.01; // $0.01 per GB per month
    const BUNNY_BANDWIDTH_COST_PER_GB = 0.005; // $0.005 per GB

    // Platform markup in EGP
    const PLATFORM_STORAGE_MARKUP_EGP = 0.52; // 0.52 EGP per GB storage (total: 1 EGP)
    const PLATFORM_BANDWIDTH_MARKUP_EGP = 0.26; // 0.26 EGP per GB bandwidth (total: 0.5 EGP)

    // Fallback exchange rate (USD to EGP) - used if API fails
    const USD_TO_EGP_RATE = 31.00;

    protected ExchangeRateService $exchangeRateService;

    public function __construct(ExchangeRateService $exchangeRateService)
    {
        $this->exchangeRateService = $exchangeRateService;
    }

    /**
     * Get current exchange rate (dynamic from API or fallback to constant)
     */
    public function getExchangeRate(): float
    {
        try {
            return $this->exchangeRateService->getRate('USD', 'EGP');
        } catch (\Exception $e) {
            // Fallback to constant if API fails
            return self::USD_TO_EGP_RATE;
        }
    }

    /**
     * Calculate upload cost based on file size
     */
    public function calculateUploadCost(float $fileSizeMB): float
    {
        $fileSizeGB = $fileSizeMB / 1024;

        // Calculate Bunny cost
        $bunnyCost = $fileSizeGB * self::BUNNY_STORAGE_COST_PER_GB;

        // Calculate platform cost (Bunny cost + markup in EGP)
        $bunnyPerGB = $fileSizeGB;
        $markupEGP = $bunnyPerGB * self::PLATFORM_STORAGE_MARKUP_EGP;
        $markupUSD = $markupEGP / $this->getExchangeRate();

        return round($bunnyCost + $markupUSD, 4);
    }

    /**
     * Calculate actual cost from Bunny statistics
     */
    public function calculateActualCost(float $storageGB, float $bandwidthGB): array
    {
        $exchangeRate = $this->getExchangeRate();

        // Calculate Bunny costs
        $bunnyStorageCost = $storageGB * self::BUNNY_STORAGE_COST_PER_GB;
        $bunnyBandwidthCost = $bandwidthGB * self::BUNNY_BANDWIDTH_COST_PER_GB;
        $totalBunnyCost = $bunnyStorageCost + $bunnyBandwidthCost;

        // Calculate platform markups in EGP
        $storageMarkupEGP = $storageGB * self::PLATFORM_STORAGE_MARKUP_EGP;
        $bandwidthMarkupEGP = $bandwidthGB * self::PLATFORM_BANDWIDTH_MARKUP_EGP;
        $totalMarkupEGP = $storageMarkupEGP + $bandwidthMarkupEGP;

        // Convert markup to USD
        $totalMarkupUSD = $totalMarkupEGP / $exchangeRate;

        // Total cost to charge tenant
        $totalPlatformCost = $totalBunnyCost + $totalMarkupUSD;

        return [
            'bunny_cost' => round($totalBunnyCost, 4),
            'platform_cost' => round($totalPlatformCost, 4),
            'storage_cost' => round($bunnyStorageCost, 4),
            'bandwidth_cost' => round($bunnyBandwidthCost, 4),
            'markup_egp' => round($totalMarkupEGP, 2),
            'markup_usd' => round($totalMarkupUSD, 4),
        ];
    }

    /**
     * Convert USD to EGP
     */
    public function usdToEgp(float $usd): float
    {
        return round($usd * $this->getExchangeRate(), 2);
    }

    /**
     * Convert EGP to USD
     */
    public function egpToUsd(float $egp): float
    {
        return round($egp / $this->getExchangeRate(), 4);
    }

    /**
     * Get pricing configuration
     */
    public function getPricingInfo(): array
    {
        return [
            'storage_per_gb_usd' => self::BUNNY_STORAGE_COST_PER_GB,
            'bandwidth_per_gb_usd' => self::BUNNY_BANDWIDTH_COST_PER_GB,
            'storage_markup_egp' => self::PLATFORM_STORAGE_MARKUP_EGP,
            'bandwidth_markup_egp' => self::PLATFORM_BANDWIDTH_MARKUP_EGP,
            'usd_to_egp_rate' => $this->getExchangeRate(),
        ];
    }
}

