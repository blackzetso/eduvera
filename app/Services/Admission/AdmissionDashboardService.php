<?php

namespace App\Services\Admission;

use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionVisit;
use App\Support\Admission\AdmissionDecision;
use App\Support\Admission\AdmissionInboxAttention;
use App\Support\Admission\AdmissionStage;
use App\Support\Admission\AdmissionStatus;
use Illuminate\Database\Eloquent\Builder;

class AdmissionDashboardService
{
    public function __construct(
        protected AdmissionProfileService $profiles,
        protected AdmissionEngagementService $engagements,
    ) {}

    /**
     * Legacy global metrics (unfiltered).
     */
    public function metrics(): array
    {
        return $this->inboxMetrics([]);
    }

    /**
     * Filter-aware inbox metrics — single source of truth for KPI, funnel, and priority queue.
     *
     * @return array<string, mixed>
     */
    public function inboxMetrics(array $filters = []): array
    {
        $today = now()->toDateString();
        $base = $this->profiles->inboxQuery($filters);
        $totalFiltered = (clone $base)->count();

        $applicationsToday = (clone $base)->whereDate('created_at', $today)->count();

        $openApplications = (clone $base)->where('status', AdmissionStatus::OPEN)->count();

        $visitsToday = (clone $base)->whereHas('latestVisit', function (Builder $q) use ($today) {
            $q->whereDate('scheduled_date', $today);
        })->count();

        $readyForConversion = (clone $base)
            ->where('decision', AdmissionDecision::ACCEPTED)
            ->where('pipeline_stage', AdmissionStage::APPLICATION)
            ->where('status', AdmissionStatus::OPEN)
            ->count();

        $missingTargetGrade = (clone $base)
            ->where('status', AdmissionStatus::OPEN)
            ->where('pipeline_stage', AdmissionStage::APPLICATION)
            ->where(function (Builder $q) {
                $q->whereNull('target_category_id')
                    ->whereDoesntHave('primaryApplicant', fn (Builder $a) => $a->whereNotNull('target_category_id'));
            })
            ->count();

        $needsAttention = $this->countNeedsAttention($filters);

        $upcomingVisits = AdmissionVisit::query()
            ->where('scheduled_date', '>=', $today)
            ->whereIn('status', ['requested', 'confirmed'])
            ->count();

        $pipelineFunnel = $this->buildPipelineFunnel($base, $totalFiltered);
        $bottleneck = $this->resolveBottleneck($pipelineFunnel);
        $priorityQueue = $this->buildPriorityQueue($filters);

        return [
            'applications_today' => $applicationsToday,
            'upcoming_visits' => $upcomingVisits,
            'open_applications' => $openApplications,
            'visits_today' => $visitsToday,
            'ready_for_conversion' => $readyForConversion,
            'missing_target_grade' => $missingTargetGrade,
            'needs_attention' => $needsAttention,
            'total_filtered' => $totalFiltered,
            'pipeline_funnel' => $pipelineFunnel,
            'bottleneck' => $bottleneck,
            'priority_queue' => $priorityQueue,
            'engagement_metrics' => $this->engagements->metrics($filters),
        ];
    }

    protected function countNeedsAttention(array $filters): int
    {
        $rows = $this->profiles->inboxRows($filters);

        return collect($rows)->filter(fn (array $row) => AdmissionInboxAttention::needsFollowUp($row))->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildPipelineFunnel(Builder $base, int $total): array
    {
        $total = max(1, $total);

        $stages = [
            ['key' => 'campus_visit', 'label' => AdmissionStage::label(AdmissionStage::CAMPUS_VISIT), 'color' => 'primary'],
            ['key' => 'application', 'label' => AdmissionStage::label(AdmissionStage::APPLICATION), 'color' => 'warning'],
            ['key' => 'accepted', 'label' => 'مقبول', 'color' => 'success'],
            ['key' => 'converted', 'label' => 'محوّل', 'color' => 'dark'],
        ];

        $campusVisit = (clone $base)->where('pipeline_stage', AdmissionStage::CAMPUS_VISIT)->count();
        $application = (clone $base)->where('pipeline_stage', AdmissionStage::APPLICATION)->count();
        $accepted = (clone $base)
            ->where('decision', AdmissionDecision::ACCEPTED)
            ->where('status', '!=', AdmissionStatus::CONVERTED)
            ->count();
        $converted = (clone $base)
            ->where(function (Builder $q) {
                $q->where('status', AdmissionStatus::CONVERTED)
                    ->orWhere('decision', AdmissionDecision::CONVERTED);
            })
            ->count();

        $counts = [
            'campus_visit' => $campusVisit,
            'application' => $application,
            'accepted' => $accepted,
            'converted' => $converted,
        ];

        return collect($stages)->map(fn (array $stage) => [
            ...$stage,
            'count' => $counts[$stage['key']] ?? 0,
            'percent' => (int) round((($counts[$stage['key']] ?? 0) / $total) * 100),
        ])->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $funnel
     * @return array<string, mixed>|null
     */
    protected function resolveBottleneck(array $funnel): ?array
    {
        if ($funnel === []) {
            return null;
        }

        return collect($funnel)->sortByDesc('count')->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildPriorityQueue(array $filters, int $limit = 5): array
    {
        $rows = $this->profiles->inboxRows($filters);

        return collect($rows)
            ->map(function (array $row) {
                $meta = AdmissionInboxAttention::priorityMeta($row);

                return [
                    'row' => $row,
                    'score' => $meta['score'],
                    'tags' => $meta['tags'],
                    'level' => $meta['level'],
                ];
            })
            ->filter(fn (array $item) => $item['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->all();
    }
}
