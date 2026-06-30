<?php

namespace App\Services\Admission;

use App\Models\Admission\AdmissionApplication;
use App\Support\Admission\AdmissionDecision;
use App\Support\Admission\AdmissionReadinessPolicy;
use App\Support\Admission\AdmissionStage;
use App\Support\Admission\AdmissionStatus;

class AdmissionMigrationAuditService
{
    public function __construct(
        protected AdmissionReadinessPolicy $readiness,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $total = AdmissionApplication::query()->count();

        if ($total === 0) {
            return $this->emptyReport();
        }

        $counts = [
            'type_a_website_visit_only' => 0,
            'type_b_campus_visit' => 0,
            'type_c_partial_applicant' => 0,
            'type_d_ready_applicant' => 0,
            'type_e_converted_student' => 0,
        ];

        $issues = [
            'missing_applicant' => 0,
            'missing_contact' => 0,
            'missing_target_category' => 0,
            'website_at_campus_visit' => 0,
            'accepted_not_ready' => 0,
            'converted_without_student_id' => 0,
        ];

        AdmissionApplication::query()
            ->with(['applicants', 'contacts', 'documents'])
            ->orderBy('id')
            ->chunkById(200, function ($applications) use (&$counts, &$issues) {
                foreach ($applications as $application) {
                    $type = $this->classify($application);
                    $counts[$type]++;

                    $this->collectIssues($application, $issues);
                }
            });

        $percentages = collect($counts)->mapWithKeys(fn ($count, $key) => [
            $key => $total > 0 ? round(($count / $total) * 100, 1) : 0.0,
        ])->all();

        $risks = $this->migrationRisks($counts, $issues, $total);

        return [
            'generated_at' => now()->toIso8601String(),
            'total_records' => $total,
            'counts' => $counts,
            'percentages' => $percentages,
            'labels' => [
                'type_a_website_visit_only' => 'Website Visit Only',
                'type_b_campus_visit' => 'Campus Visit',
                'type_c_partial_applicant' => 'Partial Applicant',
                'type_d_ready_applicant' => 'Ready Applicant',
                'type_e_converted_student' => 'Converted Student',
            ],
            'data_quality_issues' => $issues,
            'migration_risks' => $risks,
            'recommendations' => $this->recommendations($counts, $issues, $total),
        ];
    }

    protected function classify(AdmissionApplication $application): string
    {
        if (
            $application->converted_student_id
            || $application->status === AdmissionStatus::CONVERTED
            || $application->decision === AdmissionDecision::CONVERTED
        ) {
            return 'type_e_converted_student';
        }

        if ($application->pipeline_stage === AdmissionStage::APPLICATION) {
            $ready = $this->readiness
                ->evaluate($application, AdmissionReadinessPolicy::CONTEXT_CONVERSION)
                ->ready;

            if (
                $application->decision === AdmissionDecision::ACCEPTED
                && $application->status === AdmissionStatus::OPEN
                && $ready
            ) {
                return 'type_d_ready_applicant';
            }

            return 'type_c_partial_applicant';
        }

        if (
            $application->pipeline_stage === AdmissionStage::CAMPUS_VISIT
            && $application->source_channel === 'website_visit'
        ) {
            return 'type_a_website_visit_only';
        }

        if ($application->pipeline_stage === AdmissionStage::CAMPUS_VISIT) {
            return 'type_b_campus_visit';
        }

        return 'type_b_campus_visit';
    }

    /**
     * @param  array<string, int>  $issues
     */
    protected function collectIssues(AdmissionApplication $application, array &$issues): void
    {
        if ($application->applicants->isEmpty()) {
            $issues['missing_applicant']++;
        }

        if ($application->contacts->isEmpty()) {
            $issues['missing_contact']++;
        }

        $applicant = $application->applicants->first();
        $categoryId = $application->target_category_id ?? $applicant?->target_category_id;
        if (! $categoryId && $application->pipeline_stage === AdmissionStage::APPLICATION) {
            $issues['missing_target_category']++;
        }

        if (
            $application->source_channel === 'website_visit'
            && $application->pipeline_stage === AdmissionStage::CAMPUS_VISIT
        ) {
            $issues['website_at_campus_visit']++;
        }

        if (
            $application->decision === AdmissionDecision::ACCEPTED
            && $application->pipeline_stage === AdmissionStage::APPLICATION
            && ! $this->readiness->evaluate($application, AdmissionReadinessPolicy::CONTEXT_CONVERSION)->ready
        ) {
            $issues['accepted_not_ready']++;
        }

        if (
            ($application->status === AdmissionStatus::CONVERTED || $application->decision === AdmissionDecision::CONVERTED)
            && ! $application->converted_student_id
        ) {
            $issues['converted_without_student_id']++;
        }
    }

    /**
     * @param  array<string, int>  $counts
     * @param  array<string, int>  $issues
     * @return array<int, string>
     */
    protected function migrationRisks(array $counts, array $issues, int $total): array
    {
        $risks = [];

        $websiteRatio = $total > 0 ? ($counts['type_a_website_visit_only'] / $total) : 0;
        if ($websiteRatio > 0.5) {
            $risks[] = 'أكثر من 50% من السجلات هي زيارات موقع فقط — يجب فصل Prospect عن Applicant قبل التحويل الجماعي.';
        }

        if ($issues['converted_without_student_id'] > 0) {
            $risks[] = 'يوجد سجلات محوّلة بدون converted_student_id — يجب إصلاحها قبل أي refactor.';
        }

        if ($issues['accepted_not_ready'] > 0) {
            $risks[] = 'يوجد طلبات مقبولة غير جاهزة للتحويل — قد تسبب ازدواجية عند فصل Applicant.';
        }

        if ($issues['missing_applicant'] > 0 || $issues['missing_contact'] > 0) {
            $risks[] = 'سجلات بدون متقدم أو جهة اتصال — بيانات غير صالحة للتحويل التلقائي.';
        }

        return $risks;
    }

    /**
     * @param  array<string, int>  $counts
     * @param  array<string, int>  $issues
     * @return array<int, string>
     */
    protected function recommendations(array $counts, array $issues, int $total): array
    {
        $items = [];

        if ($counts['type_a_website_visit_only'] > 0) {
            $items[] = sprintf(
                'راجع %d سجل Website Visit Only قبل Phase 1 (Engagements) — مرشحون لكيان Prospect.',
                $counts['type_a_website_visit_only'],
            );
        }

        if ($counts['type_c_partial_applicant'] > 0) {
            $items[] = sprintf(
                'أكمل بيانات %d Partial Applicant قبل فصل Applicant aggregate.',
                $counts['type_c_partial_applicant'],
            );
        }

        if ($issues['website_at_campus_visit'] > 0) {
            $items[] = 'وثّق أن website_visit intake ينشئ campus_visit مباشرة — هذا يؤثر على تعريف Prospect.';
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyReport(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'total_records' => 0,
            'counts' => [
                'type_a_website_visit_only' => 0,
                'type_b_campus_visit' => 0,
                'type_c_partial_applicant' => 0,
                'type_d_ready_applicant' => 0,
                'type_e_converted_student' => 0,
            ],
            'percentages' => [],
            'labels' => [],
            'data_quality_issues' => [],
            'migration_risks' => ['لا توجد سجلات قبول في قاعدة البيانات.'],
            'recommendations' => [],
        ];
    }
}
