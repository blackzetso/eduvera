<?php

namespace App\Modules\Canteen\Notifications;

use App\Modules\Canteen\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CanteenSettlementFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Sale $sale,
        public string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (config('canteen.notifications.admin_email') && $notifiable->email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Canteen sale settlement failed')
            ->line('A canteen POS sale failed during wallet settlement.')
            ->line("Sale: {$this->sale->sale_number}")
            ->line("Student: {$this->sale->student_name} ({$this->sale->student_id_ref})")
            ->line("Reason: {$this->reason}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'canteen_settlement_failed',
            'sale_id' => $this->sale->id,
            'sale_number' => $this->sale->sale_number,
            'student_id_ref' => $this->sale->student_id_ref,
            'student_name' => $this->sale->student_name,
            'reason' => $this->reason,
            'message' => "فشلت عملية بيع كافتيريا رقم {$this->sale->sale_number}: {$this->reason}",
        ];
    }
}
