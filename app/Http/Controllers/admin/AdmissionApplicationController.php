<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionApplicant;
use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionContact;
use App\Models\Admission\AdmissionDocument;
use App\Models\Admission\AdmissionVisit;
use App\Services\Admission\AdmissionAssignmentService;
use App\Services\Admission\AdmissionConversionService;
use App\Services\Admission\AdmissionDashboardService;
use App\Services\Admission\AdmissionDecisionService;
use App\Services\Admission\AdmissionDocumentService;
use App\Services\Admission\AdmissionNoteService;
use App\Services\Admission\AdmissionPipelineService;
use App\Services\Admission\AdmissionProfileService;
use App\Services\Admission\AdmissionVisitCommandService;
use App\Services\Admission\AdmissionVisitService;
use App\Services\StudentEnrollmentService;
use App\Support\Admission\AdmissionDocumentStatus;
use App\Support\Admission\AdmissionReadinessPolicy;
use App\Support\Admission\AdmissionStage;
use App\Support\Category\CategoryTreeForForms;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdmissionApplicationController extends Controller
{
    public function __construct(
        protected AdmissionProfileService $profiles,
        protected AdmissionDashboardService $dashboard,
        protected AdmissionPipelineService $pipeline,
        protected AdmissionAssignmentService $assignments,
        protected AdmissionNoteService $notes,
        protected AdmissionDocumentService $documents,
        protected AdmissionVisitService $visits,
        protected AdmissionDecisionService $decisions,
        protected AdmissionConversionService $conversion,
        protected StudentEnrollmentService $studentEnrollments,
        protected AdmissionReadinessPolicy $readiness,
    ) {}

    public function visits(Request $request, AdmissionVisitCommandService $visitCommand)
    {
        $filters = $visitCommand->normalizeFilters([
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'status' => $request->input('status', ''),
            'search' => $request->input('search', ''),
            'assigned_to' => $request->input('assigned_to', ''),
            'page' => $request->input('page', 1),
            'per_page' => $request->input('per_page', 25),
        ]);

        $calendarPayload = $visitCommand->calendarVisits($filters);

        return Inertia::render('Admin/theme1/Admissions/Visits/Index', [
            'visits' => $visitCommand->paginatedVisits($filters),
            'calendarVisits' => $calendarPayload['visits'],
            'calendarMeta' => $calendarPayload['meta'],
            'followUpVisits' => $visitCommand->followUpQueue($filters),
            'metrics' => $visitCommand->metrics($filters),
            'filters' => $filters,
            'filterOptions' => $this->profiles->filterOptions(),
        ]);
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', AdmissionApplication::class);

        $filters = [
            'stage' => $request->input('stage', ''),
            'status' => $request->input('status', ''),
            'academic_year' => $request->input('academic_year', ''),
            'assigned_to' => $request->input('assigned_to', ''),
            'search' => $request->input('search', ''),
        ];

        $cleanFilters = array_filter($filters, fn ($v) => $v !== '' && $v !== null);

        return Inertia::render('Admin/theme1/Admissions/Index', [
            'applications' => $this->profiles->paginatedInbox(
                $cleanFilters,
                min(100, max(10, (int) $request->input('per_page', 25)))
            ),
            'filters' => array_merge($filters, [
                'page' => max(1, (int) $request->input('page', 1)),
                'per_page' => min(100, max(10, (int) $request->input('per_page', 25))),
            ]),
            'filterOptions' => $this->profiles->filterOptions(),
            'metrics' => $this->dashboard->inboxMetrics($cleanFilters),
        ]);
    }

    public function show(AdmissionApplication $admission)
    {
        $this->authorize('view', $admission);

        return Inertia::render('Admin/theme1/Admissions/Show', [
            'workspace' => $this->profiles->forWorkspaceHub($admission),
            'filterOptions' => $this->profiles->filterOptions(),
            'categories' => CategoryTreeForForms::build(),
        ]);
    }

    public function accept(Request $request, AdmissionApplication $admission)
    {
        $this->authorize('accept', $admission);
        $this->assertMutable($admission);

        $data = $request->validate([
            'reason' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:2000',
        ]);

        $this->decisions->accept($admission, $data['reason'] ?? null, $data['notes'] ?? null, $request->user()?->id);

        return redirect()
            ->to(route('admin.admissions.show', $admission).'?tab=overview')
            ->with('success', 'تم قبول الطلب بنجاح');
    }

    public function reject(Request $request, AdmissionApplication $admission)
    {
        $this->authorize('reject', $admission);
        $this->assertMutable($admission);

        $data = $request->validate([
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:2000',
        ]);

        $this->decisions->reject($admission, $data['reason'], $data['notes'] ?? null, $request->user()?->id);

        return redirect()
            ->to(route('admin.admissions.show', $admission).'?tab=overview')
            ->with('success', 'تم رفض الطلب بنجاح');
    }

    public function waitlist(Request $request, AdmissionApplication $admission)
    {
        $this->authorize('waitlist', $admission);
        $this->assertMutable($admission);

        $data = $request->validate([
            'reason' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:2000',
        ]);

        $this->decisions->waitlist($admission, $data['reason'] ?? null, $data['notes'] ?? null, $request->user()?->id);

        return redirect()
            ->to(route('admin.admissions.show', $admission).'?tab=overview')
            ->with('success', 'تم إدراج الطلب في قائمة الانتظار');
    }

    public function withdraw(Request $request, AdmissionApplication $admission)
    {
        $this->authorize('withdraw', $admission);
        $this->assertMutable($admission);

        $data = $request->validate([
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:2000',
        ]);

        $this->decisions->withdraw($admission, $data['reason'], $data['notes'] ?? null, $request->user()?->id);

        return redirect()
            ->to(route('admin.admissions.show', $admission).'?tab=overview')
            ->with('success', 'تم تسجيل انسحاب الطلب');
    }

    public function convertToStudent(Request $request, AdmissionApplication $admission)
    {
        $this->authorize('convert', $admission);
        $this->assertMutable($admission);

        $summary = $this->conversion->convert($admission, $request->user()?->id);

        if (! empty($summary['redirect_url'])) {
            return redirect()
                ->to($summary['redirect_url'])
                ->with('success', $summary['already_converted']
                    ? 'الطلب محوّل مسبقاً — تم فتح ملف الطالب'
                    : 'تم تحويل الطلب إلى طالب بنجاح');
        }

        return redirect()
            ->to(route('admin.admissions.show', $admission).'?tab=overview')
            ->with('success', 'تم تحويل الطلب');
    }

    public function transitionStage(Request $request, AdmissionApplication $admission)
    {
        $this->authorize('manage', $admission);
        $this->assertMutable($admission);

        $data = $request->validate([
            'to_stage' => [
                'required',
                'string',
                Rule::in(AdmissionStage::phaseA()),
                function (string $attribute, mixed $value, \Closure $fail) use ($admission): void {
                    if ($value === $admission->pipeline_stage) {
                        $fail('يجب اختيار مرحلة مختلفة عن المرحلة الحالية.');
                    }
                },
            ],
            'reason' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:2000',
        ]);

        $previousStage = $admission->pipeline_stage;

        if ($data['to_stage'] === AdmissionStage::APPLICATION) {
            $this->readiness->assertReady($admission, AdmissionReadinessPolicy::CONTEXT_LEAD);
        }

        try {
            $updated = $this->pipeline->transition(
                $admission,
                $data['to_stage'],
                $data['reason'] ?? null,
                $data['notes'] ?? null,
                $request->user()?->id,
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'to_stage' => $e->getMessage(),
            ]);
        }

        if ($updated->pipeline_stage === $previousStage) {
            throw ValidationException::withMessages([
                'to_stage' => 'لم يتم تغيير المرحلة. اختر مرحلة جديدة.',
            ]);
        }

        return redirect()
            ->to(route('admin.admissions.show', $admission).'?tab=timeline')
            ->with('success', 'تم تحديث مرحلة الطلب بنجاح');
    }

    public function assignOfficer(Request $request, AdmissionApplication $admission)
    {
        $this->assertMutable($admission);

        $data = $request->validate([
            'assigned_to_user_id' => 'nullable|integer|exists:users,id',
            'notes' => 'nullable|string|max:2000',
        ]);

        $this->assignments->assign(
            $admission,
            $data['assigned_to_user_id'] ?? null,
            $data['notes'] ?? null,
            $request->user()?->id,
        );

        return redirect()
            ->to(route('admin.admissions.show', $admission).'?tab=overview')
            ->with('success', 'تم تعيين مسؤول القبول بنجاح');
    }

    public function storeNote(Request $request, AdmissionApplication $admission)
    {
        $this->assertMutable($admission);

        $data = $request->validate([
            'content' => 'required|string|max:5000',
            'visibility' => 'nullable|in:internal,team',
        ]);

        $this->notes->store(
            $admission,
            $data['content'],
            $data['visibility'] ?? 'internal',
        );

        return redirect()
            ->to(route('admin.admissions.show', $admission).'?tab=timeline')
            ->with('success', 'تمت إضافة الملاحظة بنجاح');
    }

    public function updateApplicant(Request $request, AdmissionApplication $admission, AdmissionApplicant $applicant)
    {
        $this->assertMutable($admission);
        abort_unless($applicant->admission_application_id === $admission->id, 404);

        if ($request->input('target_category_id') === '') {
            $request->merge(['target_category_id' => null]);
        }

        $data = $request->validate([
            'first_name' => 'nullable|string|max:100',
            'father_name' => 'nullable|string|max:100',
            'grandfather_name' => 'nullable|string|max:100',
            'gender' => 'nullable|in:male,female',
            'date_of_birth' => 'nullable|date',
            'current_grade_label' => 'nullable|string|max:100',
            'target_category_id' => 'nullable|exists:categories,id',
            'national_id' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:5000',
        ]);

        if (! empty($data['target_category_id'])) {
            $data['target_stage_label'] = $this->deriveTargetStageLabel((int) $data['target_category_id']);
        }

        $applicant->update(array_filter($data, fn ($v) => $v !== null));

        $categoryId = $data['target_category_id'] ?? $applicant->target_category_id;
        if ($categoryId) {
            $admission->update(['target_category_id' => $categoryId]);
        }

        return redirect()
            ->to(route('admin.admissions.show', $admission).'?tab=applicant')
            ->with('success', 'تم تحديث بيانات المتقدم بنجاح');
    }

    public function updateContact(Request $request, AdmissionApplication $admission, AdmissionContact $contact)
    {
        $this->assertMutable($admission);
        abort_unless($contact->admission_application_id === $admission->id, 404);

        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:40',
            'national_id' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'relationship_type' => 'nullable|string|max:30',
            'communication_preferences' => 'nullable|array',
            'communication_preferences.email' => 'nullable|boolean',
            'communication_preferences.phone' => 'nullable|boolean',
            'communication_preferences.sms' => 'nullable|boolean',
            'communication_preferences.whatsapp' => 'nullable|boolean',
        ]);

        $contact->update(array_filter($data, fn ($v) => $v !== null));

        return redirect()
            ->to(route('admin.admissions.show', $admission).'?tab=contacts')
            ->with('success', 'تم تحديث بيانات جهة الاتصال بنجاح');
    }

    public function updateVisit(Request $request, AdmissionApplication $admission, AdmissionVisit $visit)
    {
        $this->assertMutable($admission);
        abort_unless($visit->admission_application_id === $admission->id, 404);

        $data = $request->validate([
            'scheduled_date' => 'nullable|date',
            'scheduled_time' => 'nullable|string|max:20',
            'status' => 'nullable|string|max:20',
            'outcome' => 'nullable|string|max:40',
            'attendance_status' => 'nullable|string|max:40',
            'notes' => 'nullable|string|max:5000',
            'follow_up_notes' => 'nullable|string|max:5000',
        ]);

        if (array_key_exists('scheduled_date', $data) || array_key_exists('scheduled_time', $data)) {
            $this->readiness->assertReady($admission, AdmissionReadinessPolicy::CONTEXT_VISIT_SCHEDULE);
        }

        $this->visits->update($visit, $data);

        return redirect()
            ->to(route('admin.admissions.show', $admission).'?tab=visits')
            ->with('success', 'تم تحديث بيانات الزيارة بنجاح');
    }

    public function updateDocument(Request $request, AdmissionApplication $admission, AdmissionDocument $document)
    {
        $this->authorize('manage', $admission);
        $this->assertMutable($admission);
        abort_unless($document->admission_application_id === $admission->id, 404);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(AdmissionDocumentStatus::all())],
            'notes' => 'nullable|string|max:2000',
        ]);

        $this->documents->updateStatus(
            $document,
            $data['status'],
            $data['notes'] ?? null,
        );

        return redirect()
            ->to(route('admin.admissions.show', $admission).'?tab=documents')
            ->with('success', 'تم تحديث حالة المستند بنجاح');
    }

    public function reviewDocument(Request $request, AdmissionApplication $admission, AdmissionDocument $document)
    {
        $this->authorize('manage', $admission);
        $this->assertMutable($admission);
        abort_unless($document->admission_application_id === $admission->id, 404);

        $data = $request->validate([
            'action' => 'required|in:approve,reupload,reject',
            'notes' => 'nullable|string|max:2000',
            'reupload_reason' => 'required_if:action,reupload|nullable|string|max:2000',
            'reject_reason' => 'required_if:action,reject|nullable|string|max:2000',
        ]);

        $notes = match ($data['action']) {
            'reupload' => $data['reupload_reason'] ?? $data['notes'],
            'reject' => $data['reject_reason'] ?? $data['notes'],
            default => $data['notes'] ?? null,
        };

        $this->documents->review($document, $data['action'], $notes);

        $message = match ($data['action']) {
            'approve' => 'تم اعتماد المستند بنجاح',
            'reupload' => 'تم طلب إعادة رفع المستند',
            'reject' => 'تم رفض المستند',
        };

        return redirect()
            ->to(route('admin.admissions.show', $admission).'?tab=documents')
            ->with('success', $message);
    }

    public function uploadDocument(Request $request, AdmissionApplication $admission, AdmissionDocument $document)
    {
        $this->authorize('manage', $admission);
        $this->assertMutable($admission);
        abort_unless($document->admission_application_id === $admission->id, 404);

        $request->validate([
            'file' => 'required|file',
        ]);

        $this->documents->uploadFile($document, $request->file('file'));

        return redirect()
            ->to(route('admin.admissions.show', $admission).'?tab=documents')
            ->with('success', 'تم رفع المستند بنجاح');
    }

    public function removeDocumentFile(AdmissionApplication $admission, AdmissionDocument $document)
    {
        $this->authorize('manage', $admission);
        $this->assertMutable($admission);
        abort_unless($document->admission_application_id === $admission->id, 404);

        $this->documents->removeFile($document);

        return redirect()
            ->to(route('admin.admissions.show', $admission).'?tab=documents')
            ->with('success', 'تم حذف ملف المستند');
    }

    public function downloadDocument(Request $request, AdmissionApplication $admission, AdmissionDocument $document)
    {
        $this->authorize('view', $admission);
        abort_unless($document->admission_application_id === $admission->id, 404);

        $path = $this->documents->downloadPath($document);

        if (! $path) {
            abort(404, 'الملف غير موجود');
        }

        $filename = $document->original_filename ?? basename($document->file_path);

        if ($request->boolean('preview')) {
            return response()->file($path, [
                'Content-Type' => $document->mime_type ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $filename).'"',
            ]);
        }

        return response()->download($path, $filename);
    }

    protected function assertMutable(AdmissionApplication $admission): void
    {
        if ($admission->isReadOnly()) {
            throw ValidationException::withMessages([
                'application' => 'هذا الطلب محوّل ولا يمكن تعديله.',
            ]);
        }
    }

    protected function deriveTargetStageLabel(int $categoryId): ?string
    {
        return $this->studentEnrollments
            ->resolvePlacementFromCategory($categoryId)['stage_name'] ?? null;
    }
}
