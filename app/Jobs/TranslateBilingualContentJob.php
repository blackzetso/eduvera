<?php

namespace App\Jobs;

use App\Models\Website\WebsiteSetting;
use App\Services\Translation\BilingualAutoTranslationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class TranslateBilingualContentJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $settingKey,
        public array $payload,
    ) {}

    public function handle(BilingualAutoTranslationService $translator): void
    {
        try {
            $translated = $translator->translatePayload($this->payload);
            WebsiteSetting::writeValue($this->settingKey, $translated);
        } catch (\Throwable $e) {
            Log::error('Queued bilingual translation failed', [
                'setting_key' => $this->settingKey,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
