<?php

namespace App\Services\Admission;

use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionNote;
use App\Models\Admission\AdmissionVisit;
use App\Support\Admission\AdmissionEngagementChannel;
use App\Support\Admission\AdmissionEngagementStatus;
use App\Support\Admission\AdmissionEngagementType;
use Illuminate\Support\Carbon;

class AdmissionEngagementBackfillService
{
    public function __construct(
        protected AdmissionEngagementService $engagements,
    ) {}

    /**
     * @return array<string, int>
     */
    public function run(): array
    {
        $counts = [
            'website_form' => 0,
            'campus_visit_scheduled' => 0,
            'campus_visit_completed' => 0,
            'campus_visit_cancelled' => 0,
            'notes' => 0,
            'follow_ups' => 0,
        ];

        AdmissionApplication::query()
            ->with(['visits', 'internalNotes'])
            ->orderBy('id')
            ->chunkById(100, function ($applications) use (&$counts) {
                foreach ($applications as $application) {
                    if ($this->shouldBackfillWebsiteIntake($application)) {
                        $this->backfillWebsiteIntake($application);
                        $counts['website_form']++;
                    }

                    foreach ($application->visits as $visit) {
                        $counts = $this->backfillVisit($application, $visit, $counts);
                    }

                    foreach ($application->internalNotes as $note) {
                        $counts = $this->backfillNote($application, $note, $counts);
                    }
                }
            });

        return $counts;
    }

    protected function shouldBackfillWebsiteIntake(AdmissionApplication $application): bool
    {
        return in_array($application->source_channel, ['website_visit', 'form_builder'], true);
    }

    protected function backfillWebsiteIntake(AdmissionApplication $application): void
    {
        $this->engagements->record([
            'admission_application_id' => $application->id,
            'type' => AdmissionEngagementType::WEBSITE_FORM,
            'channel' => AdmissionEngagementChannel::WEBSITE,
            'status' => AdmissionEngagementStatus::COMPLETED,
            'subject' => 'استفسار من الموقع',
            'message' => $application->notes,
            'completed_at' => $application->created_at,
            'metadata' => [
                'source_key' => "application:{$application->id}:website_form",
                'source' => $application->source_channel ?? 'website',
                'backfill' => true,
            ],
        ]);
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    protected function backfillVisit(AdmissionApplication $application, AdmissionVisit $visit, array $counts): array
    {
        $scheduledAt = $this->visitScheduledAt($visit);

        $this->engagements->record([
            'admission_application_id' => $application->id,
            'type' => AdmissionEngagementType::CAMPUS_VISIT,
            'channel' => AdmissionEngagementChannel::VISIT,
            'status' => AdmissionEngagementStatus::SCHEDULED,
            'subject' => 'زيارة الحرم',
            'message' => $visit->notes,
            'scheduled_at' => $scheduledAt,
            'metadata' => [
                'source_key' => "visit:{$visit->id}:scheduled",
                'visit_id' => $visit->id,
                'backfill' => true,
            ],
        ]);
        $counts['campus_visit_scheduled']++;

        if (in_array($visit->status, ['completed'], true) || $visit->attendance_status === 'attended') {
            $this->engagements->record([
                'admission_application_id' => $application->id,
                'type' => AdmissionEngagementType::CAMPUS_VISIT,
                'channel' => AdmissionEngagementChannel::VISIT,
                'status' => AdmissionEngagementStatus::COMPLETED,
                'subject' => 'زيارة الحرم — حضور',
                'message' => $visit->notes,
                'scheduled_at' => $scheduledAt,
                'completed_at' => $visit->updated_at ?? $scheduledAt,
                'metadata' => [
                    'source_key' => "visit:{$visit->id}:completed",
                    'visit_id' => $visit->id,
                    'outcome' => $visit->outcome,
                    'backfill' => true,
                ],
            ]);
            $counts['campus_visit_completed']++;
        }

        if ($visit->status === 'cancelled') {
            $this->engagements->record([
                'admission_application_id' => $application->id,
                'type' => AdmissionEngagementType::CAMPUS_VISIT,
                'channel' => AdmissionEngagementChannel::VISIT,
                'status' => AdmissionEngagementStatus::CANCELLED,
                'subject' => 'زيارة الحرم — ملغاة',
                'message' => $visit->notes,
                'scheduled_at' => $scheduledAt,
                'metadata' => [
                    'source_key' => "visit:{$visit->id}:cancelled",
                    'visit_id' => $visit->id,
                    'backfill' => true,
                ],
            ]);
            $counts['campus_visit_cancelled']++;
        }

        if (! empty($visit->follow_up_notes)) {
            $this->engagements->record([
                'admission_application_id' => $application->id,
                'type' => AdmissionEngagementType::FOLLOW_UP,
                'channel' => AdmissionEngagementChannel::INTERNAL,
                'status' => AdmissionEngagementStatus::COMPLETED,
                'subject' => 'متابعة بعد الزيارة',
                'message' => $visit->follow_up_notes,
                'completed_at' => $visit->updated_at ?? $scheduledAt,
                'created_by' => null,
                'metadata' => [
                    'source_key' => "visit:{$visit->id}:follow_up",
                    'visit_id' => $visit->id,
                    'backfill' => true,
                ],
            ]);
            $counts['follow_ups']++;
        }

        return $counts;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    protected function backfillNote(AdmissionApplication $application, AdmissionNote $note, array $counts): array
    {
        $this->engagements->record([
            'admission_application_id' => $application->id,
            'type' => AdmissionEngagementType::NOTE,
            'channel' => AdmissionEngagementChannel::INTERNAL,
            'status' => AdmissionEngagementStatus::COMPLETED,
            'subject' => 'ملاحظة داخلية',
            'message' => $note->content,
            'completed_at' => $note->created_at,
            'created_by' => $note->author_user_id,
            'metadata' => [
                'source_key' => "note:{$note->id}",
                'note_id' => $note->id,
                'visibility' => $note->visibility,
                'backfill' => true,
            ],
        ]);
        $counts['notes']++;

        return $counts;
    }

    protected function visitScheduledAt(AdmissionVisit $visit): ?Carbon
    {
        if (! $visit->scheduled_date) {
            return $visit->created_at;
        }

        $time = $visit->scheduled_time ? substr((string) $visit->scheduled_time, 0, 5) : '09:00';

        return Carbon::parse($visit->scheduled_date->format('Y-m-d').' '.$time);
    }
}
