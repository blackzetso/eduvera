<?php

namespace App\Services\Admission;

use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionEngagement;
use App\Support\Admission\AdmissionEngagementChannel;
use App\Support\Admission\AdmissionEngagementStatus;
use App\Support\Admission\AdmissionEngagementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AdmissionEngagementService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function record(array $data): AdmissionEngagement
    {
        $sourceKey = $data['metadata']['source_key'] ?? null;

        if ($sourceKey) {
            $existing = AdmissionEngagement::query()
                ->where('admission_application_id', $data['admission_application_id'] ?? null)
                ->where('metadata->source_key', $sourceKey)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return AdmissionEngagement::query()->create([
            'admission_application_id' => $data['admission_application_id'] ?? null,
            'type' => $data['type'],
            'channel' => $data['channel'],
            'status' => $data['status'] ?? AdmissionEngagementStatus::PENDING,
            'subject' => $data['subject'] ?? null,
            'message' => $data['message'] ?? null,
            'scheduled_at' => isset($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : null,
            'completed_at' => isset($data['completed_at']) ? Carbon::parse($data['completed_at']) : null,
            'created_by' => $data['created_by'] ?? auth()->id(),
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function schedule(AdmissionApplication $application, array $data): AdmissionEngagement
    {
        return $this->record([
            ...$data,
            'admission_application_id' => $application->id,
            'status' => AdmissionEngagementStatus::SCHEDULED,
            'scheduled_at' => $data['scheduled_at'] ?? now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function complete(AdmissionEngagement $engagement, array $data = []): AdmissionEngagement
    {
        $engagement->update([
            'status' => AdmissionEngagementStatus::COMPLETED,
            'completed_at' => $data['completed_at'] ?? now(),
            'message' => $data['message'] ?? $engagement->message,
            'metadata' => array_merge($engagement->metadata ?? [], $data['metadata'] ?? []),
        ]);

        return $engagement->fresh();
    }

    public function cancel(AdmissionEngagement $engagement, ?string $reason = null): AdmissionEngagement
    {
        $metadata = $engagement->metadata ?? [];

        if ($reason) {
            $metadata['cancel_reason'] = $reason;
        }

        $engagement->update([
            'status' => AdmissionEngagementStatus::CANCELLED,
            'metadata' => $metadata,
        ]);

        return $engagement->fresh();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function timeline(AdmissionApplication $application): array
    {
        $engagements = $application->engagements()
            ->with('creator')
            ->orderByDesc('created_at')
            ->get();

        return $engagements->map(fn (AdmissionEngagement $e) => $this->formatTimelineEvent($e))->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $admissionEvents
     * @param  array<int, array<string, mixed>>  $engagementEvents
     * @return array<int, array<string, mixed>>
     */
    public function mergeTimeline(array $admissionEvents, array $engagementEvents): array
    {
        return collect($admissionEvents)
            ->concat($engagementEvents)
            ->sortByDesc(fn (array $event) => $event['occurred_at'] ?? '')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function metrics(array $filters = []): array
    {
        $base = $this->filteredEngagementQuery($filters);

        $total = (clone $base)->count();
        $completed = (clone $base)->where('status', AdmissionEngagementStatus::COMPLETED)->count();
        $pendingFollowups = (clone $base)
            ->where('type', AdmissionEngagementType::FOLLOW_UP)
            ->whereIn('status', [AdmissionEngagementStatus::PENDING, AdmissionEngagementStatus::SCHEDULED])
            ->count();
        $visitsCompleted = (clone $base)
            ->where('type', AdmissionEngagementType::CAMPUS_VISIT)
            ->where('status', AdmissionEngagementStatus::COMPLETED)
            ->count();

        return [
            'total_engagements' => $total,
            'completed_engagements' => $completed,
            'pending_followups' => $pendingFollowups,
            'visits_completed' => $visitsCompleted,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatTimelineEvent(AdmissionEngagement $engagement): array
    {
        $occurredAt = $engagement->completed_at
            ?? $engagement->scheduled_at
            ?? $engagement->created_at;

        return [
            'type' => 'engagement',
            'engagement_type' => $engagement->type,
            'engagement_type_label' => AdmissionEngagementType::label($engagement->type),
            'channel' => $engagement->channel,
            'channel_label' => AdmissionEngagementChannel::label($engagement->channel),
            'status' => $engagement->status,
            'status_label' => AdmissionEngagementStatus::label($engagement->status),
            'title' => $engagement->subject ?? AdmissionEngagementType::label($engagement->type),
            'description' => $engagement->message,
            'meta' => AdmissionEngagementStatus::label($engagement->status),
            'performed_by' => $engagement->creator?->name,
            'occurred_at' => $occurredAt?->toIso8601String(),
            'icon' => AdmissionEngagementType::icon($engagement->type),
            'is_engagement' => true,
            'engagement_id' => $engagement->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function filteredEngagementQuery(array $filters): Builder
    {
        $query = AdmissionEngagement::query()
            ->whereNotNull('admission_application_id');

        if (! empty($filters['stage'])) {
            $query->whereHas('application', fn (Builder $q) => $q->where('pipeline_stage', $filters['stage']));
        }

        if (! empty($filters['status'])) {
            $query->whereHas('application', fn (Builder $q) => $q->where('status', $filters['status']));
        }

        if (! empty($filters['assigned_to'])) {
            $query->whereHas('application', fn (Builder $q) => $q->where('assigned_to_user_id', $filters['assigned_to']));
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('application', function (Builder $q) use ($search) {
                $q->where('reference_code', 'like', "%{$search}%")
                    ->orWhereHas('primaryContact', fn (Builder $c) => $c
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        return $query;
    }
}
