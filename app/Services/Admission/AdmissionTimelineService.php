<?php

namespace App\Services\Admission;

use App\Models\Admission\AdmissionApplication;
use App\Support\Admission\AdmissionDecision;
use App\Support\Admission\AdmissionDocumentStatus;
use App\Support\Admission\AdmissionStage;

class AdmissionTimelineService
{
    public function __construct(
        protected AdmissionEngagementService $engagements,
    ) {}

    public function build(AdmissionApplication $application): array
    {
        $application->load([
            'stageHistories.performedBy:id,name',
            'decisionHistories.performedBy:id,name',
            'convertedStudent:id,name,student_code',
            'visits',
            'assignmentHistories.fromUser:id,name',
            'assignmentHistories.toUser:id,name',
            'assignmentHistories.performedBy:id,name',
            'internalNotes.author:id,name',
            'documents.histories.performedBy:id,name',
        ]);

        $events = [];

        foreach ($application->decisionHistories as $history) {
            $events[] = [
                'type' => $history->to_decision === AdmissionDecision::CONVERTED ? 'conversion' : 'decision_change',
                'title' => $history->to_decision === AdmissionDecision::CONVERTED
                    ? 'تحويل إلى طالب'
                    : 'تغيير القرار',
                'description' => ($history->from_decision ? AdmissionDecision::label($history->from_decision).' → ' : '')
                    .AdmissionDecision::label($history->to_decision),
                'meta' => $history->reason,
                'performed_by' => $history->performedBy?->name ?? 'النظام',
                'occurred_at' => $history->effective_at?->toIso8601String(),
            ];

            if ($history->to_decision === AdmissionDecision::CONVERTED && $history->notes) {
                $summary = json_decode($history->notes, true);
                if (is_array($summary)) {
                    if (! empty($summary['student_name'])) {
                        $events[] = [
                            'type' => 'student_created',
                            'title' => 'إنشاء طالب',
                            'description' => ($summary['student_name'] ?? '').' ('.($summary['student_code'] ?? '').')',
                            'meta' => isset($summary['student_id']) ? 'معرّف: '.$summary['student_id'] : null,
                            'performed_by' => $history->performedBy?->name,
                            'occurred_at' => $history->effective_at?->toIso8601String(),
                        ];
                    }

                    if (! empty($summary['enrollment_id'])) {
                        $events[] = [
                            'type' => 'enrollment_created',
                            'title' => 'إنشاء قيد',
                            'description' => 'قيد قبول #'.$summary['enrollment_id'],
                            'meta' => 'مصدر: admission',
                            'performed_by' => $history->performedBy?->name,
                            'occurred_at' => $history->effective_at?->toIso8601String(),
                        ];
                    }

                    foreach ($summary['guardians'] ?? [] as $guardian) {
                        $events[] = [
                            'type' => 'guardian_match',
                            'title' => ($guardian['action'] ?? '') === 'matched' ? 'مطابقة ولي أمر' : 'إنشاء ولي أمر',
                            'description' => $guardian['name'] ?? '—',
                            'meta' => ($guardian['action'] ?? '') === 'matched'
                                ? 'مطابقة عبر: '.($guardian['matched_by'] ?? '—')
                                : 'ولي أمر جديد',
                            'performed_by' => $history->performedBy?->name,
                            'occurred_at' => $history->effective_at?->toIso8601String(),
                        ];
                    }
                }
            }
        }

        foreach ($application->stageHistories as $history) {
            $events[] = [
                'type' => 'stage_change',
                'title' => 'تغيير مرحلة',
                'description' => ($history->from_stage ? AdmissionStage::label($history->from_stage).' → ' : '')
                    .AdmissionStage::label($history->to_stage),
                'meta' => $history->reason,
                'performed_by' => $history->performedBy?->name ?? 'النظام',
                'occurred_at' => $history->effective_at?->toIso8601String(),
            ];
        }

        foreach ($application->visits as $visit) {
            $event = $this->visitTimelineEvent($visit);
            $events[] = $event;

            if ($visit->outcome) {
                $events[] = [
                    'type' => 'visit_outcome',
                    'title' => 'تسجيل نتيجة الزيارة',
                    'description' => config('admissions.visit_outcomes.'.$visit->outcome.'.label_ar') ?? $visit->outcome,
                    'meta' => $visit->follow_up_notes,
                    'performed_by' => null,
                    'occurred_at' => ($visit->completed_at ?? $visit->updated_at)?->toIso8601String(),
                ];
            }
        }

        foreach ($application->assignmentHistories as $assignment) {
            $events[] = [
                'type' => 'assignment',
                'title' => 'تعيين مسؤول',
                'description' => ($assignment->fromUser?->name ?? '—').' → '.($assignment->toUser?->name ?? '—'),
                'meta' => $assignment->notes,
                'performed_by' => $assignment->performedBy?->name,
                'occurred_at' => $assignment->effective_at?->toIso8601String(),
            ];
        }

        foreach ($application->internalNotes as $note) {
            $events[] = [
                'type' => 'note',
                'title' => 'ملاحظة داخلية',
                'description' => \Illuminate\Support\Str::limit($note->content, 120),
                'meta' => $note->visibility,
                'performed_by' => $note->author?->name,
                'occurred_at' => $note->created_at?->toIso8601String(),
            ];
        }

        foreach ($application->documents as $document) {
            foreach ($document->histories as $history) {
                $events[] = [
                    'type' => 'document',
                    'title' => $this->documentHistoryTitle($history),
                    'description' => AdmissionDocumentStatus::label($history->from_status ?? '')
                        .' → '
                        .AdmissionDocumentStatus::label($history->to_status),
                    'meta' => $history->notes,
                    'performed_by' => $history->performedBy?->name,
                    'occurred_at' => $history->effective_at?->toIso8601String(),
                ];
            }
        }

        $admissionEvents = collect($events)
            ->filter(fn ($e) => ! empty($e['occurred_at']))
            ->values()
            ->all();

        $engagementEvents = $this->engagements->timeline($application);

        return $this->engagements->mergeTimeline($admissionEvents, $engagementEvents);
    }

    protected function documentHistoryTitle($history): string
    {
        return match ($history->to_status) {
            AdmissionDocumentStatus::APPROVED => 'تم اعتماد المستند',
            AdmissionDocumentStatus::REJECTED => 'تم رفض المستند',
            AdmissionDocumentStatus::REUPLOAD_REQUIRED => 'طلب إعادة رفع المستند',
            default => 'تحديث حالة المستند',
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function visitTimelineEvent($visit): array
    {
        $type = match (true) {
            $visit->status === 'cancelled' => 'visit_cancelled',
            $visit->outcome === 'rescheduled' => 'visit_rescheduled',
            $visit->attendance_status === 'attended', $visit->status === 'completed' => 'visit_attended',
            default => 'visit_scheduled',
        };

        $title = match ($type) {
            'visit_cancelled' => 'إلغاء زيارة',
            'visit_rescheduled' => 'إعادة جدولة زيارة',
            'visit_attended' => 'حضور زيارة',
            default => 'جدولة زيارة',
        };

        $statusLabel = config('admissions.visit_statuses.'.$visit->status.'.label_ar') ?? $visit->status;

        return [
            'type' => $type,
            'title' => $title,
            'description' => trim(($visit->scheduled_date?->format('Y-m-d') ?? '').' '.($visit->scheduled_time ?? '')),
            'meta' => $statusLabel.($visit->attendance_status ? ' · '.(config('admissions.visit_attendance_statuses.'.$visit->attendance_status.'.label_ar') ?? $visit->attendance_status) : ''),
            'performed_by' => null,
            'occurred_at' => ($visit->completed_at ?? $visit->created_at)?->toIso8601String(),
        ];
    }
}
