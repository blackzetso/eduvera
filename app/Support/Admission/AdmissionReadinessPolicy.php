<?php

namespace App\Support\Admission;

use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionContact;
use App\Models\StudentEnrollment;
use App\Services\Admission\AdmissionDocumentService;
use Illuminate\Validation\ValidationException;

class AdmissionReadinessPolicy
{
    /** @var array<string, AdmissionReadinessResult> */
    protected array $evaluationCache = [];

    public const CONTEXT_VISIT_SCHEDULE = 'visit_schedule';

    public const CONTEXT_LEAD = 'lead';

    public const CONTEXT_APPLICATION = 'application';

    public const CONTEXT_DECISION = 'decision';

    public const CONTEXT_CONVERSION = 'conversion';

    public function __construct(
        protected AdmissionDocumentService $documents,
    ) {}

    public function evaluate(AdmissionApplication $application, string $context): AdmissionReadinessResult
    {
        if ($application->id) {
            $cacheKey = $application->id.'_'.$context;

            if (isset($this->evaluationCache[$cacheKey])) {
                return $this->evaluationCache[$cacheKey];
            }
        }

        $application->loadMissing(['applicants', 'contacts', 'documents']);

        $checks = match ($context) {
            self::CONTEXT_VISIT_SCHEDULE => $this->visitScheduleChecks($application),
            self::CONTEXT_LEAD => $this->leadChecks($application),
            self::CONTEXT_APPLICATION => $this->applicationChecks($application),
            self::CONTEXT_DECISION => $this->decisionChecks($application),
            self::CONTEXT_CONVERSION => $this->conversionChecks($application),
            default => throw new \InvalidArgumentException("Unknown readiness context: {$context}"),
        };

        $ready = collect($checks)->every(
            fn (array $check) => $check['ok'] || ! $check['blocking'],
        );

        $result = new AdmissionReadinessResult($ready, $checks, $context);

        if ($application->id) {
            $this->evaluationCache[$application->id.'_'.$context] = $result;
        }

        return $result;
    }

    public function assertReady(AdmissionApplication $application, string $context): AdmissionReadinessResult
    {
        $result = $this->evaluate($application, $context);

        if (! $result->ready) {
            throw ValidationException::withMessages([
                'conversion' => $result->blockingErrors(),
                'readiness' => $result->blockingErrors(),
            ]);
        }

        return $result;
    }

    /**
     * @return array<int, array{id: string, label: string, ok: bool, blocking: bool, severity: string}>
     */
    protected function visitScheduleChecks(AdmissionApplication $application): array
    {
        $applicant = $application->applicants->first();
        $contact = $this->resolvePrimaryContact($application);

        return [
            $this->check('primary_contact_exists', 'جهة الاتصال الأساسية موجودة', $contact !== null),
            $this->check('contact_name', 'اسم جهة الاتصال', $contact && trim((string) $contact->name) !== ''),
            $this->check('phone_exists', 'رقم الهاتف', $contact && trim((string) $contact->phone) !== ''),
            $this->check('applicant_exists', 'المتقدم موجود', $applicant !== null),
            $this->check('applicant_name', 'اسم المتقدم', $applicant && trim((string) $applicant->first_name) !== ''),
        ];
    }

    /**
     * Minimum prospect/lead data required to advance into the Application pipeline stage.
     *
     * @return array<int, array{id: string, label: string, ok: bool, blocking: bool, severity: string}>
     */
    protected function leadChecks(AdmissionApplication $application): array
    {
        $applicant = $application->applicants->first();
        $contact = $this->resolvePrimaryContact($application);
        $categoryId = $application->target_category_id ?? $applicant?->target_category_id;
        $interestGradeOk = (bool) $categoryId
            || ($applicant && trim((string) $applicant->current_grade_label) !== '');

        return [
            $this->check('applicant_exists', 'المتقدم موجود', $applicant !== null),
            $this->check('applicant_name', 'اسم المتقدم', $applicant && trim((string) $applicant->first_name) !== ''),
            $this->check('primary_contact_exists', 'جهة الاتصال الأساسية', $contact !== null),
            $this->check('contact_name', 'اسم ولي الأمر', $contact && trim((string) $contact->name) !== ''),
            $this->check('contact_phone', 'هاتف جهة الاتصال', $contact && trim((string) $contact->phone) !== ''),
            $this->check('contact_email', 'بريد جهة الاتصال', $contact && trim((string) $contact->email) !== ''),
            $this->check('interest_grade', 'الصف/الفئة المستهدفة (اهتمام)', $interestGradeOk),
            $this->check('source_channel', 'مصدر الطلب', filled($application->source_channel)),
        ];
    }

    /**
     * @return array<int, array{id: string, label: string, ok: bool, blocking: bool, severity: string}>
     */
    protected function applicationChecks(AdmissionApplication $application): array
    {
        $applicant = $application->applicants->first();
        $contact = $this->resolvePrimaryContact($application);
        $categoryId = $application->target_category_id ?? $applicant?->target_category_id;

        return [
            $this->check('applicant_exists', 'المتقدم موجود', $applicant !== null),
            $this->check('applicant_dob', 'تاريخ ميلاد المتقدم', $applicant && $applicant->date_of_birth !== null),
            $this->check('applicant_gender', 'جنس المتقدم', $applicant && filled($applicant->gender)),
            $this->check('target_category', 'الصف/الفئة المستهدفة', (bool) $categoryId),
            $this->check('primary_contact_exists', 'جهة الاتصال الأساسية', $contact !== null),
            $this->check('contact_phone', 'هاتف جهة الاتصال', $contact && trim((string) $contact->phone) !== ''),
            $this->check('contact_email', 'بريد جهة الاتصال', $contact && trim((string) $contact->email) !== ''),
        ];
    }

    /**
     * @return array<int, array{id: string, label: string, ok: bool, blocking: bool, severity: string}>
     */
    protected function decisionChecks(AdmissionApplication $application): array
    {
        $applicationReady = $this->evaluate($application, self::CONTEXT_APPLICATION);
        $documentsOk = $this->documentsComplete($application);

        return [
            $this->check(
                'application_ready',
                'اكتمال بيانات الطلب',
                $applicationReady->ready,
            ),
            $this->documentsCheck($application, 'decision', $documentsOk),
            $this->check(
                'no_blocking_warnings',
                'لا توجد تحذيرات معيقة',
                $applicationReady->warnings() === [],
            ),
        ];
    }

    /**
     * @return array<int, array{id: string, label: string, ok: bool, blocking: bool, severity: string}>
     */
    protected function conversionChecks(AdmissionApplication $application): array
    {
        $applicant = $application->applicants->first();
        $contact = $this->resolvePrimaryContact($application);
        $categoryId = $application->target_category_id ?? $applicant?->target_category_id;
        $documentsOk = $this->documentsComplete($application);
        $documentsBlocking = (bool) config('admissions.readiness.documents_required_for_conversion', false);

        $checks = [
            $this->check('applicant_exists', 'المتقدم موجود', $applicant !== null),
            $this->check('applicant_first_name', 'الاسم الأول للمتقدم', $applicant && trim((string) $applicant->first_name) !== ''),
            $this->check('applicant_dob', 'تاريخ ميلاد المتقدم', $applicant && $applicant->date_of_birth !== null),
            $this->check('applicant_gender', 'جنس المتقدم', $applicant && filled($applicant->gender)),
            $this->check('primary_contact_exists', 'جهة الاتصال الأساسية موجودة', $contact !== null),
            $this->check('primary_contact_name', 'اسم جهة الاتصال الأساسية', $contact && trim((string) $contact->name) !== ''),
            $this->check('target_category', 'الصف/الفئة المستهدفة', (bool) $categoryId),
            $this->check(
                'pipeline_stage_application',
                'مرحلة الطلب: طلب التقديم',
                $application->pipeline_stage === AdmissionStage::APPLICATION,
            ),
            $this->check(
                'decision_accepted',
                'قرار القبول: مقبول',
                $application->decision === AdmissionDecision::ACCEPTED,
            ),
            $this->check(
                'not_converted',
                'لم يُحوَّل مسبقاً',
                ! $application->converted_student_id && $application->decision !== AdmissionDecision::CONVERTED,
            ),
            $this->check(
                'no_existing_enrollment',
                'لا يوجد قيد مسجل مسبقاً',
                ! $this->hasExistingEnrollment($application),
            ),
        ];

        $checks[] = $this->documentsCheck(
            $application,
            'conversion',
            $documentsOk,
            blocking: $documentsBlocking,
            severity: $documentsBlocking ? 'error' : 'warning',
        );

        return $checks;
    }

    protected function hasExistingEnrollment(AdmissionApplication $application): bool
    {
        if (! $application->id) {
            return false;
        }

        return StudentEnrollment::query()
            ->where('admission_reference_id', $application->id)
            ->exists();
    }

    protected function documentsComplete(AdmissionApplication $application): bool
    {
        $summary = $this->documents->summaryFor($application);

        return (bool) ($summary['complete'] ?? false);
    }

    /**
     * @return array{id: string, label: string, ok: bool, blocking: bool, severity: string, detail: string|null}
     */
    protected function documentsCheck(
        AdmissionApplication $application,
        string $blockingContext,
        bool $ok,
        bool $blocking = true,
        string $severity = 'error',
    ): array {
        $presentation = $this->documents->readinessPresentation($application, $blockingContext);

        return $this->check(
            'documents_complete',
            $presentation['label'],
            $ok,
            blocking: $blocking,
            severity: $severity,
            detail: $presentation['detail'],
        );
    }

    protected function resolvePrimaryContact(AdmissionApplication $application): ?AdmissionContact
    {
        return $application->contacts->firstWhere('is_primary', true)
            ?? $application->contacts->first();
    }

    /**
     * @return array{id: string, label: string, ok: bool, blocking: bool, severity: string, detail: string|null}
     */
    protected function check(
        string $id,
        string $label,
        bool $ok,
        bool $blocking = true,
        string $severity = 'error',
        ?string $detail = null,
    ): array {
        return [
            'id' => $id,
            'label' => $label,
            'ok' => $ok,
            'blocking' => $blocking,
            'severity' => $ok ? 'success' : $severity,
            'detail' => $detail,
        ];
    }
}
