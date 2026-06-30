<?php

namespace App\Services\Admission;

use App\Models\Admission\AdmissionVisit;
use App\Support\Admission\AdmissionStage;
use App\Support\Admission\AdmissionStatus;
use App\Support\Admission\AdmissionVisitAttention;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
class AdmissionVisitCommandService
{
    public function normalizeFilters(array $filters): array
    {
        $today = now();
        $dateFrom = $filters['date_from'] ?? $today->copy()->subDays(30)->toDateString();
        $dateTo = $filters['date_to'] ?? $today->copy()->addDays(60)->toDateString();

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'status' => trim((string) ($filters['status'] ?? '')),
            'search' => trim((string) ($filters['search'] ?? '')),
            'assigned_to' => trim((string) ($filters['assigned_to'] ?? '')),
            'page' => max(1, (int) ($filters['page'] ?? 1)),
            'per_page' => min(100, max(10, (int) ($filters['per_page'] ?? 25))),
        ];
    }

    public function visitQuery(array $filters = []): Builder
    {
        $filters = $this->normalizeFilters($filters);

        $query = AdmissionVisit::query()
            ->with([
                'application.assignedTo:id,name',
                'application.applicants.targetCategory',
                'application.contacts',
                'application.targetCategory',
            ]);

        if ($filters['date_from']) {
            $query->whereDate('scheduled_date', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->whereDate('scheduled_date', '<=', $filters['date_to']);
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['assigned_to'] !== '') {
            $query->whereHas('application', function (Builder $q) use ($filters) {
                $q->where('assigned_to_user_id', (int) $filters['assigned_to']);
            });
        }

        if ($filters['search'] !== '') {
            $like = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($like) {
                $q->whereHas('application', function (Builder $app) use ($like) {
                    $app->where('reference_code', 'like', $like)
                        ->orWhereHas('applicants', fn (Builder $a) => $a->where('first_name', 'like', $like))
                        ->orWhereHas('contacts', fn (Builder $c) => $c->where('name', 'like', $like)->orWhere('phone', 'like', $like));
                });
            });
        }

        return $query
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time');
    }

    /**
     * @return array{visits: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function calendarVisits(array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $limit = (int) config('admissions.visits_calendar_limit', 2000);

        $query = $this->visitQuery($filters);
        $totalInRange = (clone $query)->count();
        $truncated = $totalInRange > $limit;

        $visits = $query
            ->when($truncated, fn (Builder $q) => $q->limit($limit))
            ->get()
            ->map(fn (AdmissionVisit $visit) => $this->enrichVisitRow($visit))
            ->values()
            ->all();

        return [
            'visits' => $visits,
            'meta' => [
                'total_in_range' => $totalInRange,
                'returned' => count($visits),
                'limit' => $limit,
                'truncated' => $truncated,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function followUpQueue(array $filters = [], int $limit = 100): array
    {
        $filters = $this->normalizeFilters($filters);
        $cutoff = now()->subDays(3)->toDateString();

        $rows = $this->followUpQuery($filters)
            ->whereDate('scheduled_date', '<=', $cutoff)
            ->get()
            ->map(fn (AdmissionVisit $visit) => $this->enrichVisitRow($visit))
            ->filter(fn (array $row) => $row['needs_follow_up'])
            ->sortByDesc(fn (array $row) => AdmissionVisitAttention::daysSince($row['scheduled_date']))
            ->take($limit)
            ->values()
            ->all();

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function visitRows(array $filters = [], ?int $limit = null): array
    {
        $query = $this->visitQuery($filters);

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query
            ->get()
            ->map(fn (AdmissionVisit $visit) => $this->enrichVisitRow($visit))
            ->values()
            ->all();
    }

    public function paginatedVisits(array $filters = []): LengthAwarePaginator
    {
        $filters = $this->normalizeFilters($filters);

        return $this->visitQuery($filters)
            ->paginate($filters['per_page'], ['*'], 'page', $filters['page'])
            ->withQueryString()
            ->through(fn (AdmissionVisit $visit) => $this->enrichVisitRow($visit));
    }

    /**
     * @return array<string, int>
     */
    public function metrics(array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $today = now()->toDateString();

        $base = $this->visitQuery($filters);

        $visitsToday = (clone $base)->whereDate('scheduled_date', $today)->count();

        $upcoming = (clone $base)
            ->whereDate('scheduled_date', '>', $today)
            ->whereIn('status', ['requested', 'confirmed'])
            ->count();

        $attended = (clone $base)->where(function (Builder $q) {
            $q->where('attendance_status', 'attended')->orWhere('status', 'completed');
        })->count();

        $noShow = (clone $base)->where(function (Builder $q) {
            $q->where('status', 'no_show')->orWhere('attendance_status', 'no_show');
        })->count();

        $interested = (clone $base)->whereIn('outcome', AdmissionVisitAttention::POSITIVE_OUTCOMES)->count();

        $followUp = $this->followUpCount($filters);

        return [
            'visits_today' => $visitsToday,
            'upcoming_visits' => $upcoming,
            'attended' => $attended,
            'no_show' => $noShow,
            'interested_families' => $interested,
            'follow_up_required' => $followUp,
        ];
    }

    protected function followUpCount(array $filters): int
    {
        $cutoff = now()->subDays(3)->toDateString();

        return $this->followUpQuery($filters)
            ->whereDate('scheduled_date', '<=', $cutoff)
            ->count();
    }

    protected function followUpQuery(array $filters): Builder
    {
        $filters = $this->normalizeFilters($filters);

        return $this->visitQuery($filters)
            ->whereIn('outcome', AdmissionVisitAttention::POSITIVE_OUTCOMES)
            ->whereHas('application', function (Builder $q) {
                $q->where('pipeline_stage', AdmissionStage::CAMPUS_VISIT)
                    ->where('status', AdmissionStatus::OPEN);
            });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function needsFollowUp(array $row): bool
    {
        return AdmissionVisitAttention::needsFollowUp($row);
    }

    /**
     * @return array<string, mixed>
     */
    protected function enrichVisitRow(AdmissionVisit $visit): array
    {
        return AdmissionVisitAttention::enrichRow($this->mapVisitRow($visit));
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapVisitRow(AdmissionVisit $visit): array
    {
        $application = $visit->application;
        $applicant = $application?->applicants->first();
        $contact = $application?->contacts->firstWhere('is_primary', true)
            ?? $application?->contacts->first();

        $targetCategory = $application?->targetCategory?->name
            ?? $applicant?->targetCategory?->name
            ?? $applicant?->current_grade_label;

        return [
            'id' => $visit->id,
            'application_id' => $visit->admission_application_id,
            'reference_code' => $application?->reference_code,
            'scheduled_date' => $visit->scheduled_date?->format('Y-m-d'),
            'scheduled_time' => $visit->scheduled_time,
            'status' => $visit->status,
            'status_label' => config('admissions.visit_statuses.'.$visit->status.'.label_ar') ?? $visit->status,
            'outcome' => $visit->outcome,
            'outcome_label' => $visit->outcome
                ? (config('admissions.visit_outcomes.'.$visit->outcome.'.label_ar') ?? $visit->outcome)
                : null,
            'attendance_status' => $visit->attendance_status,
            'attendance_label' => $visit->attendance_status
                ? (config('admissions.visit_attendance_statuses.'.$visit->attendance_status.'.label_ar') ?? $visit->attendance_status)
                : null,
            'notes' => $visit->notes,
            'follow_up_notes' => $visit->follow_up_notes,
            'completed_at' => $visit->completed_at?->toIso8601String(),
            'created_at' => $visit->created_at?->toIso8601String(),
            'updated_at' => $visit->updated_at?->toIso8601String(),
            'applicant_name' => $applicant?->displayName() ?: $applicant?->first_name,
            'parent_name' => $contact?->name,
            'parent_phone' => $contact?->phone,
            'parent_email' => $contact?->email,
            'target_grade' => $targetCategory,
            'pipeline_stage' => $application?->pipeline_stage,
            'pipeline_stage_label' => $application
                ? AdmissionStage::label($application->pipeline_stage)
                : null,
            'application_status' => $application?->status,
            'application_status_label' => $application
                ? AdmissionStatus::label($application->status)
                : null,
            'assigned_officer' => $application?->assignedTo?->name,
            'assigned_officer_id' => $application?->assigned_to_user_id,
            'last_activity_at' => $application?->updated_at?->toIso8601String(),
        ];
    }
}
