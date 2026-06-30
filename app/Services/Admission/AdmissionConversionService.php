<?php

namespace App\Services\Admission;

use App\Models\Admission\AdmissionApplicant;
use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionContact;
use App\Models\Admission\AdmissionDecisionHistory;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\Admission\AdmissionDecision;
use App\Support\Admission\AdmissionReadinessPolicy;
use App\Support\Admission\AdmissionStage;
use App\Support\Admission\AdmissionStatus;
use App\Support\Student\GuardianRelationship;
use App\Support\Student\StudentStatus;
use App\Services\StudentCodeService;
use App\Services\StudentEnrollmentService;
use App\Services\StudentGuardianService;
use App\Services\StudentStatusService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdmissionConversionService
{
    public function __construct(
        protected AdmissionGuardianMatcherService $matcher,
        protected StudentCodeService $studentCodes,
        protected StudentEnrollmentService $enrollments,
        protected StudentGuardianService $guardians,
        protected StudentStatusService $studentStatus,
        protected AdmissionReadinessPolicy $readiness,
        protected \App\Services\PlatformAuditService $audit,
    ) {}

    /**
     * @return array{ready: bool, errors: array<int, string>, checks: array<int, array<string, mixed>>, warnings: array<int, string>, completion_percentage: int, context: string}
     */
    public function assessReadiness(AdmissionApplication $application): array
    {
        return $this->readiness
            ->evaluate($application, AdmissionReadinessPolicy::CONTEXT_CONVERSION)
            ->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function convert(AdmissionApplication $application, ?int $userId = null): array
    {
        return DB::transaction(function () use ($application, $userId) {
            $application = AdmissionApplication::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($application->converted_student_id) {
                return $this->existingConversionSummary($application);
            }

            $this->readiness->assertReady($application, AdmissionReadinessPolicy::CONTEXT_CONVERSION);

            $application->load(['applicants', 'contacts']);
            $applicant = $application->applicants->first();
            $primaryContact = $this->resolvePrimaryContact($application);
            $categoryId = (int) ($application->target_category_id ?? $applicant->target_category_id);
            $performedBy = $userId ?? Auth::id();
            $enrollmentDate = now()->toDateString();

            $guardianResult = $this->resolveOrCreateGuardian($primaryContact);
            $student = $this->createStudent($application, $applicant, $categoryId, $enrollmentDate);
            $enrollment = $this->enrollments->recordAdmissionEnrollment(
                $student,
                $categoryId,
                $application->id,
                $enrollmentDate,
                'تحويل من طلب قبول '.$application->reference_code,
            );

            $this->guardians->sync($student, [[
                'guardian_id' => $guardianResult['guardian_id'],
                'relationship_type' => $this->mapRelationshipType($primaryContact->relationship_type),
                'is_primary' => true,
                'is_emergency_contact' => (bool) $primaryContact->is_emergency_contact,
                'is_pickup_authorized' => (bool) $primaryContact->is_pickup_authorized,
                'is_financial_responsible' => (bool) $primaryContact->is_financial_responsible,
            ]]);

            $applicant->update(['converted_user_id' => $student->id]);

            if (! $primaryContact->matched_guardian_id) {
                $primaryContact->update(['matched_guardian_id' => $guardianResult['guardian_id']]);
            }

            $fromDecision = $application->decision;
            $effectiveAt = now();

            $summary = [
                'student_id' => $student->id,
                'student_code' => $student->student_code,
                'student_name' => $student->name,
                'enrollment_id' => $enrollment->id,
                'guardians' => [$guardianResult],
            ];

            $application->update([
                'decision' => AdmissionDecision::CONVERTED,
                'decision_at' => $effectiveAt,
                'decision_by_user_id' => $performedBy,
                'status' => AdmissionStatus::CONVERTED,
                'converted_student_id' => $student->id,
                'converted_at' => $effectiveAt,
                'converted_by_user_id' => $performedBy,
            ]);

            AdmissionDecisionHistory::create([
                'admission_application_id' => $application->id,
                'from_decision' => $fromDecision,
                'to_decision' => AdmissionDecision::CONVERTED,
                'reason' => 'تحويل إلى طالب',
                'notes' => json_encode($summary, JSON_UNESCAPED_UNICODE),
                'performed_by_user_id' => $performedBy,
                'effective_at' => $effectiveAt,
            ]);

            $this->audit->record(
                'admissions',
                'convert',
                $application,
                ['decision' => $fromDecision, 'converted_student_id' => null],
                ['decision' => AdmissionDecision::CONVERTED, 'converted_student_id' => $student->id],
                $summary,
                $performedBy,
            );

            return [
                'already_converted' => false,
                'application_id' => $application->id,
                'reference_code' => $application->reference_code,
                'student_id' => $student->id,
                'student_code' => $student->student_code,
                'student_name' => $student->name,
                'enrollment_id' => $enrollment->id,
                'guardians' => [$guardianResult],
                'redirect_url' => route('admin.students.show', $student),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function existingConversionSummary(AdmissionApplication $application): array
    {
        $student = User::query()->find($application->converted_student_id);
        $enrollment = StudentEnrollment::query()
            ->where('admission_reference_id', $application->id)
            ->first();

        return [
            'already_converted' => true,
            'application_id' => $application->id,
            'reference_code' => $application->reference_code,
            'student_id' => $student?->id,
            'student_code' => $student?->student_code,
            'student_name' => $student?->name,
            'enrollment_id' => $enrollment?->id,
            'guardians' => [],
            'redirect_url' => $student ? route('admin.students.show', $student) : null,
        ];
    }

    protected function resolvePrimaryContact(AdmissionApplication $application): ?AdmissionContact
    {
        return $application->contacts->firstWhere('is_primary', true)
            ?? $application->contacts->first();
    }

    /**
     * @return array{guardian_id: int, action: string, name: string, matched_by: ?string}
     */
    protected function resolveOrCreateGuardian(AdmissionContact $contact): array
    {
        $existing = $this->matcher->resolveGuardianUser($contact);

        if ($existing) {
            return [
                'guardian_id' => $existing['user']->id,
                'action' => 'matched',
                'name' => $existing['user']->name,
                'matched_by' => $existing['matched_by'],
            ];
        }

        $email = $this->uniqueGuardianEmail($contact);
        $guardian = User::create([
            'name' => $contact->name,
            'email' => $email,
            'phone' => $contact->phone,
            'national_id' => $contact->national_id,
            'password' => Hash::make(Str::random(32)),
            'user_type' => 'guardian',
        ]);

        return [
            'guardian_id' => $guardian->id,
            'action' => 'created',
            'name' => $guardian->name,
            'matched_by' => null,
        ];
    }

    protected function createStudent(
        AdmissionApplication $application,
        AdmissionApplicant $applicant,
        int $categoryId,
        string $enrollmentDate,
    ): User {
        if ($applicant->converted_user_id) {
            $existing = User::query()
                ->where('id', $applicant->converted_user_id)
                ->where('user_type', 'student')
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        if ($applicant->national_id) {
            $byNationalId = User::query()
                ->where('user_type', 'student')
                ->where('national_id', $applicant->national_id)
                ->first();

            if ($byNationalId) {
                throw ValidationException::withMessages([
                    'conversion' => 'يوجد طالب مسجل بنفس الرقم القومي.',
                ]);
            }
        }

        $displayName = $applicant->displayName() ?: $applicant->first_name;
        $email = $this->uniqueStudentEmail($application, $applicant);

        $student = User::create([
            'name' => $displayName,
            'first_name' => $applicant->first_name,
            'father_name' => $applicant->father_name,
            'grandfather_name' => $applicant->grandfather_name,
            'email' => $email,
            'phone' => null,
            'national_id' => $applicant->national_id,
            'date_of_birth' => $applicant->date_of_birth,
            'gender' => $applicant->gender,
            'category_id' => $categoryId,
            'user_type' => 'student',
            'role' => 'student',
            'password' => Hash::make(Str::random(32)),
            'student_code' => $this->studentCodes->generate(),
            'enrollment_date' => $enrollmentDate,
            'student_status' => StudentStatus::PENDING,
        ]);

        $this->studentStatus->recordInitial($student);

        return $student;
    }

    protected function uniqueStudentEmail(AdmissionApplication $application, AdmissionApplicant $applicant): string
    {
        $base = 'adm-'.Str::slug($application->reference_code, '-').'@internal.school';
        $email = $base;
        $suffix = 1;

        while (User::query()->where('email', $email)->exists()) {
            $email = str_replace('@internal.school', "-{$suffix}@internal.school", $base);
            $suffix++;
        }

        return $email;
    }

    protected function uniqueGuardianEmail(AdmissionContact $contact): string
    {
        if ($contact->email && ! User::query()->where('email', $contact->email)->exists()) {
            return $contact->email;
        }

        $base = 'guardian-'.($contact->id ?: 'new').'-'.Str::lower(Str::random(8)).'@internal.school';
        $email = $base;
        $suffix = 1;

        while (User::query()->where('email', $email)->exists()) {
            $email = str_replace('@internal.school', "-{$suffix}@internal.school", $base);
            $suffix++;
        }

        return $email;
    }

    protected function mapRelationshipType(?string $type): string
    {
        $allowed = GuardianRelationship::types();

        return in_array($type, $allowed, true) ? $type : GuardianRelationship::GUARDIAN;
    }
}
