<?php

namespace App\Services;

use App\Models\StorageWallet;
use App\Models\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletService
{
    protected PricingService $pricingService;
    protected BunnyLibraryService $bunnyService;

    public function __construct(PricingService $pricingService, BunnyLibraryService $bunnyService)
    {
        $this->pricingService = $pricingService;
        $this->bunnyService = $bunnyService;
    }

    /**
     * Get or create wallet for tenant
     */
    public function getOrCreateWallet(): StorageWallet
    {
        $wallet = StorageWallet::first();

        if (!$wallet) {
            $wallet = StorageWallet::create([
                'balance' => 0,
                'total_credited' => 0,
                'total_debited' => 0,
                'is_activated' => false,
                'initial_credit_granted' => false,
            ]);
        }

        return $wallet;
    }

    /**
     * Activate wallet and grant initial credit
     */
    public function activateWallet(?float $initialCredit = 20.00): StorageWallet
    {
        $wallet = $this->getOrCreateWallet();

        if (!$wallet->is_activated) {
            DB::transaction(function () use ($wallet, $initialCredit) {
                $wallet->activate();
                $wallet->grantInitialCredit($initialCredit);
            });
        }

        return $wallet->fresh();
    }

    /**
     * Check if wallet has sufficient balance
     */
    public function checkBalance(float $requiredAmount): bool
    {
        $wallet = $this->getOrCreateWallet();
        return $wallet->hasBalance($requiredAmount);
    }

    /**
     * Get current balance
     */
    public function getBalance(): float
    {
        $wallet = $this->getOrCreateWallet();
        return (float) $wallet->balance;
    }

    /**
     * Deduct cost for file upload
     */
    public function deductForUpload(float $fileSizeMB, File $file): array
    {
        $wallet = $this->getOrCreateWallet();

        // Calculate cost
        $cost = $this->pricingService->calculateUploadCost($fileSizeMB);

        if (!$wallet->hasBalance($cost)) {
            return [
                'success' => false,
                'message' => 'Insufficient balance',
                'required' => $cost,
                'available' => $wallet->balance,
            ];
        }

        // Deduct from wallet
        $transaction = $wallet->deduct(
            $cost,
            "Video upload: {$file->name}",
            $file,
            [
                'file_size_mb' => $fileSizeMB,
                'file_size_gb' => $fileSizeMB / 1024,
                'estimated' => true,
            ]
        );

        // Create consumption record
        $file->consumption()->create([
            'storage_gb' => $fileSizeMB / 1024,
            'bandwidth_gb' => 0,
            'bunny_cost' => ($fileSizeMB / 1024) * PricingService::BUNNY_STORAGE_COST_PER_GB,
            'platform_cost' => $cost,
        ]);

        return [
            'success' => true,
            'cost' => $cost,
            'balance' => $wallet->balance,
            'transaction_id' => $transaction->id,
        ];
    }

    /**
     * Credit wallet (for recharge)
     */
    public function creditWallet(float $amount, string $description, $related = null): array
    {
        $wallet = $this->getOrCreateWallet();

        $transaction = $wallet->credit($amount, $description, $related);

        return [
            'success' => true,
            'amount' => $amount,
            'balance' => $wallet->balance,
            'transaction_id' => $transaction->id,
        ];
    }

    /**
     * Sync consumption from Bunny and adjust wallet
     */
    public function syncConsumptionFromBunny(): array
    {
        try {
            $wallet = $this->getOrCreateWallet();
            
            // تحقق: هل تمت المزامنة اليوم بالفعل؟
            if ($wallet->last_synced_at && $wallet->last_synced_at->isToday()) {
                return [
                    'success' => true,
                    'message' => 'تمت المزامنة اليوم بالفعل',
                    'no_sync_needed' => true,
                    'last_synced_at' => $wallet->last_synced_at,
                    'storage_gb' => $wallet->last_synced_storage_gb,
                    'bandwidth_gb' => $wallet->last_synced_bandwidth_gb,
                    'storage_cost' => 0,
                    'bandwidth_cost' => 0,
                    'total_cost' => 0,
                ];
            }

            $syncResult = $this->bunnyService->syncConsumption();

            if (!$syncResult['success']) {
                return $syncResult;
            }

            // Calculate actual costs
            $costs = $this->pricingService->calculateActualCost(
                $syncResult['storage_gb'],
                $syncResult['bandwidth_gb']
            );

            // Get all files and update their consumption
            $files = File::where('type', 'bunny_stream')->get();

            foreach ($files as $file) {
                $consumption = $file->consumption;
                if ($consumption) {
                    $fileCost = $costs['platform_cost'] / max($files->count(), 1);
                    
                    // Update consumption record
                    $consumption->updateConsumption(
                        $syncResult['storage_gb'] / max($files->count(), 1),
                        $syncResult['bandwidth_gb'] / max($files->count(), 1),
                        $costs['bunny_cost'] / max($files->count(), 1),
                        $fileCost
                    );
                }
            }
            
            // حساب الفرق من آخر Total مخزن في الـ wallet
            $previousStorageGB = $wallet->last_synced_storage_gb ?? 0;
            $previousBandwidthGB = $wallet->last_synced_bandwidth_gb ?? 0;
            
            // حساب الفرق (الاستهلاك الجديد فقط)
            $newStorageGB = $syncResult['storage_gb'] - $previousStorageGB;
            $newBandwidthGB = $syncResult['bandwidth_gb'] - $previousBandwidthGB;
            
            // حساب تكلفة الفرق بالجنيه
            $storageEGP = $newStorageGB * 1.00; // 1 ج/GB
            $bandwidthEGP = $newBandwidthGB * 0.50; // 0.5 ج/GB
            
            // تحويل لدولار
            $exchangeRate = $this->pricingService->getExchangeRate();
            $storageCostUSD = $storageEGP / $exchangeRate;
            $bandwidthCostUSD = $bandwidthEGP / $exchangeRate;
            
            // معاملة منفصلة للتخزين (الفرق فقط)
            if ($storageCostUSD > 0) {
                $wallet->deduct(
                    $storageCostUSD,
                    "استهلاك التخزين - " . round($newStorageGB, 2) . " GB",
                    null,
                    [
                        'storage_gb' => $newStorageGB,
                        'total_storage_gb' => $syncResult['storage_gb'],
                        'rate_egp_per_gb' => 1.00,
                        'total_egp' => $storageEGP,
                        'sync_date' => now()->toDateTimeString(),
                    ]
                );
            }

            // معاملة منفصلة للباندويث (الفرق فقط)
            if ($bandwidthCostUSD > 0) {
                $wallet->deduct(
                    $bandwidthCostUSD,
                    "استهلاك الباندويث - " . round($newBandwidthGB, 2) . " GB",
                    null,
                    [
                        'bandwidth_gb' => $newBandwidthGB,
                        'total_bandwidth_gb' => $syncResult['bandwidth_gb'],
                        'rate_egp_per_gb' => 0.50,
                        'total_egp' => $bandwidthEGP,
                        'sync_date' => now()->toDateTimeString(),
                    ]
                );
            }

            // تحديث آخر Total في الـ wallet (للمزامنة القادمة)
            $wallet->update([
                'last_synced_storage_gb' => $syncResult['storage_gb'],
                'last_synced_bandwidth_gb' => $syncResult['bandwidth_gb'],
                'last_synced_at' => now(),
            ]);

            return [
                'success' => true,
                'storage_gb' => $syncResult['storage_gb'],
                'bandwidth_gb' => $syncResult['bandwidth_gb'],
                'new_storage_gb' => $newStorageGB,
                'new_bandwidth_gb' => $newBandwidthGB,
                'storage_cost' => $storageCostUSD,
                'bandwidth_cost' => $bandwidthCostUSD,
                'total_cost' => $storageCostUSD + $bandwidthCostUSD,
                'costs' => $costs,
            ];
        } catch (\Exception $e) {
            Log::error('Error syncing consumption from Bunny: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Check if wallet needs activation
     */
    public function needsActivation(): bool
    {
        $wallet = StorageWallet::first();
        return !$wallet || !$wallet->is_activated;
    }
}

