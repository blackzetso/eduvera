<?php

namespace App\Modules\Canteen\Notifications;

use App\Models\User;
use App\Modules\Canteen\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CanteenPurchaseNotification extends Notification
{
    use Queueable;

    /** @var list<string> */
    protected array $channels = ['database'];

    public function __construct(
        public User $student,
        public Sale $sale,
        public array $payload = [],
    ) {}

    /**
     * @param  list<string>  $channels
     */
    public function withChannels(array $channels): self
    {
        $this->channels = $channels;

        return $this;
    }

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Canteen purchase notification')
            ->line("Student {$this->student->name} made a canteen purchase.")
            ->line("Sale: {$this->sale->sale_number}")
            ->line('Total: '.number_format((float) $this->sale->total, 2).' EGP')
            ->line('Date: '.($this->sale->sold_at?->toDateTimeString() ?? now()->toDateTimeString()));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'canteen_purchase',
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'sale_id' => $this->sale->id,
            'sale_number' => $this->sale->sale_number,
            'total' => (string) $this->sale->total,
            'sold_at' => $this->sale->sold_at?->toIso8601String(),
            'message' => "قام الطالب {$this->student->name} بعملية شراء من الكافتيريا بمبلغ {$this->sale->total} جنيه.",
            'payload' => $this->payload,
        ];
    }
}
