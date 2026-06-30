<?php

namespace App\Modules\Canteen\Services;

use App\Models\User;
use App\Modules\Canteen\Models\Sale;
use App\Services\WhatsAppService;

class CanteenWhatsAppNotifier
{
    public function __construct(protected WhatsAppService $whatsApp) {}

    public function enabled(): bool
    {
        return (bool) config('canteen.notifications.whatsapp_enabled', true)
            && config('services.whatsapp.api_key') !== '';
    }

    public function notifyPurchase(User $guardian, User $student, Sale $sale): bool
    {
        if (! $this->enabled() || ! $guardian->phone) {
            return false;
        }

        $message = sprintf(
            'إشعار كافتيريا: قام الطالب %s بعملية شراء رقم %s بمبلغ %s جنيه بتاريخ %s.',
            $student->name,
            $sale->sale_number,
            number_format((float) $sale->total, 2, '.', ''),
            $sale->sold_at?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i'),
        );

        return $this->whatsApp->sendMessage($guardian->phone, $message);
    }
}
