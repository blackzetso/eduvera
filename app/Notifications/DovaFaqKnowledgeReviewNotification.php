<?php

namespace App\Notifications;

use App\Models\DovaFaq;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DovaFaqKnowledgeReviewNotification extends Notification
{
    use Queueable;

    public function __construct(
        public DovaFaq $faq,
        public string $reminderType,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (config('dova-knowledge-governance.send_email_reminders', false) && filled($notifiable->email ?? null)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'faq_id' => $this->faq->id,
            'reminder_type' => $this->reminderType,
            'question' => $this->faq->question_en,
            'next_review_due_at' => $this->faq->next_review_due_at?->toDateString(),
            'message' => $this->messageAr(),
            'action_url' => route('admin.dova-knowledge.faqs.edit', $this->faq->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subjectAr())
            ->line($this->messageAr())
            ->action('مراجعة السؤال', route('admin.dova-knowledge.faqs.edit', $this->faq->id));
    }

    protected function subjectAr(): string
    {
        return match ($this->reminderType) {
            'due_soon' => 'تذكير: مراجعة سؤال شائع قريباً',
            'due_today' => 'مطلوب: مراجعة سؤال شائع اليوم',
            'overdue' => 'تأخر: مراجعة سؤال شائع متأخرة',
            default => 'تذكير بمراجعة المعرفة',
        };
    }

    protected function messageAr(): string
    {
        $question = mb_substr($this->faq->question_en, 0, 80);

        return match ($this->reminderType) {
            'due_soon' => "مراجعة السؤال الشائع «{$question}» مستحقة خلال 30 يوماً.",
            'due_today' => "مراجعة السؤال الشائع «{$question}» مطلوبة اليوم.",
            'overdue' => "مراجعة السؤال الشائع «{$question}» متأخرة 30 يوماً.",
            default => "يرجى مراجعة السؤال الشائع «{$question}».",
        };
    }
}
