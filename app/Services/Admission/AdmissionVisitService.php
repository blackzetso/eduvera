<?php

namespace App\Services\Admission;

use App\Models\Admission\AdmissionVisit;
use App\Support\Admission\AdmissionEngagementChannel;
use App\Support\Admission\AdmissionEngagementStatus;
use App\Support\Admission\AdmissionEngagementType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AdmissionVisitService
{
    public function __construct(
        protected AdmissionEngagementService $engagements,
    ) {}

    public function update(AdmissionVisit $visit, array $data): AdmissionVisit
    {
        $previousFollowUp = $visit->follow_up_notes;

        $visit->forceFill([
            'scheduled_date' => $data['scheduled_date'] ?? $visit->scheduled_date,
            'scheduled_time' => $data['scheduled_time'] ?? $visit->scheduled_time,
            'status' => $data['status'] ?? $visit->status,
            'outcome' => $data['outcome'] ?? $visit->outcome,
            'attendance_status' => $data['attendance_status'] ?? $visit->attendance_status,
            'notes' => $data['notes'] ?? $visit->notes,
            'follow_up_notes' => $data['follow_up_notes'] ?? $visit->follow_up_notes,
            'completed_at' => isset($data['completed_at'])
                ? Carbon::parse($data['completed_at'])
                : ($visit->completed_at ?? (in_array($data['status'] ?? $visit->status, ['completed', 'no_show'], true) ? now() : $visit->completed_at)),
        ])->save();

        $visit = $visit->fresh();
        $application = $visit->application;

        if ($application && (array_key_exists('scheduled_date', $data) || array_key_exists('scheduled_time', $data))) {
            $this->engagements->schedule($application, [
                'type' => AdmissionEngagementType::CAMPUS_VISIT,
                'channel' => AdmissionEngagementChannel::VISIT,
                'subject' => 'زيارة الحرم',
                'message' => $visit->notes,
                'scheduled_at' => $this->visitScheduledAt($visit),
                'created_by' => Auth::id(),
                'metadata' => [
                    'source_key' => "visit:{$visit->id}:scheduled",
                    'visit_id' => $visit->id,
                ],
            ]);
        }

        $newStatus = $data['status'] ?? null;
        $newAttendance = $data['attendance_status'] ?? null;

        if ($newStatus === 'completed' || $newAttendance === 'attended') {
            $this->engagements->record([
                'admission_application_id' => $application?->id,
                'type' => AdmissionEngagementType::CAMPUS_VISIT,
                'channel' => AdmissionEngagementChannel::VISIT,
                'status' => AdmissionEngagementStatus::COMPLETED,
                'subject' => 'زيارة الحرم — حضور',
                'message' => $visit->notes,
                'scheduled_at' => $this->visitScheduledAt($visit),
                'completed_at' => $visit->completed_at ?? now(),
                'created_by' => Auth::id(),
                'metadata' => [
                    'source_key' => "visit:{$visit->id}:completed",
                    'visit_id' => $visit->id,
                    'outcome' => $visit->outcome,
                ],
            ]);
        }

        if ($newStatus === 'cancelled') {
            $this->engagements->record([
                'admission_application_id' => $application?->id,
                'type' => AdmissionEngagementType::CAMPUS_VISIT,
                'channel' => AdmissionEngagementChannel::VISIT,
                'status' => AdmissionEngagementStatus::CANCELLED,
                'subject' => 'زيارة الحرم — ملغاة',
                'message' => $visit->notes,
                'scheduled_at' => $this->visitScheduledAt($visit),
                'created_by' => Auth::id(),
                'metadata' => [
                    'source_key' => "visit:{$visit->id}:cancelled",
                    'visit_id' => $visit->id,
                ],
            ]);
        }

        if (! empty($data['follow_up_notes']) && $data['follow_up_notes'] !== $previousFollowUp) {
            $this->engagements->record([
                'admission_application_id' => $application?->id,
                'type' => AdmissionEngagementType::FOLLOW_UP,
                'channel' => AdmissionEngagementChannel::INTERNAL,
                'status' => AdmissionEngagementStatus::COMPLETED,
                'subject' => 'متابعة بعد الزيارة',
                'message' => $visit->follow_up_notes,
                'completed_at' => now(),
                'created_by' => Auth::id(),
                'metadata' => [
                    'source_key' => "visit:{$visit->id}:follow_up",
                    'visit_id' => $visit->id,
                ],
            ]);
        }

        return $visit;
    }

    protected function visitScheduledAt(AdmissionVisit $visit): Carbon
    {
        if (! $visit->scheduled_date) {
            return $visit->created_at ?? now();
        }

        $time = $visit->scheduled_time ? substr((string) $visit->scheduled_time, 0, 5) : '09:00';

        return Carbon::parse($visit->scheduled_date->format('Y-m-d').' '.$time);
    }
}
