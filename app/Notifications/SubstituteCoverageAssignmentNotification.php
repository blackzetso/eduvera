<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubstituteCoverageAssignmentNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, array<string, mixed>>  $periods
     */
    public function __construct(
        public string $date,
        public string $dayName,
        public array $periods,
        public string $status = 'draft',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $count = count($this->periods);
        $first = $this->periods[0] ?? [];
        $periodLabel = $first['period_number'] ?? '—';
        $subject = $first['subject_name'] ?? '—';
        $className = $first['class_name'] ?? '';

        if ($this->status === 'approved') {
            $message = $count > 1
                ? "تم اعتماد تكليفك بتغطية {$count} حصص يوم {$this->dayName} ({$this->date})."
                : "تم اعتماد تكليفك بتغطية الحصة {$periodLabel} ({$subject}) يوم {$this->dayName} ({$this->date}).";
        } else {
            $message = "تم ترشيحك لتغطية الحصة {$periodLabel} — {$subject}"
                .($className ? " — {$className}" : '')
                ." يوم {$this->dayName} ({$this->date}). في انتظار اعتماد خطة التغطية اليومية.";
        }

        return [
            'type' => 'substitute_coverage',
            'status' => $this->status,
            'date' => $this->date,
            'day_name' => $this->dayName,
            'periods' => $this->periods,
            'periods_count' => $count,
            'message' => $message,
            'url' => route('teacher.timetables.index'),
        ];
    }
}
