<?php

namespace App\Notifications;

use App\Models\AttendanceAlert;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StudentAbsenceNotification extends Notification
{
    use Queueable;

    public function __construct(
        public User $student,
        public AttendanceAlert $alert,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'level' => $this->alert->level,
            'absences_count' => $this->alert->absences_count,
            'message' => $this->alert->level === 'critical'
                ? "تجاوز الطالب {$this->student->name} حد الغياب الحرج."
                : "تنبيه غياب للطالب {$this->student->name}.",
        ];
    }
}
