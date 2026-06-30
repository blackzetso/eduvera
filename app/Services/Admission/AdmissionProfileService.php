<?php

namespace App\Services\Admission;

use App\Models\Admission\AdmissionApplication;
use App\Models\Category;
use App\Models\User;
use App\Support\Admission\AdmissionDecision;
use App\Support\Admission\AdmissionReadinessPolicy;
use App\Support\Admission\AdmissionStage;
use App\Support\Admission\AdmissionStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AdmissionProfileService
{
    public function __construct(
        protected AdmissionDocumentService $documents,
        protected AdmissionDuplicateDetectionService $duplicates,
        protected AdmissionGuardianMatcherService $matcher,
        protected AdmissionTimelineService $timeline,
        protected AdmissionEngagementService $engagements,
        protected AdmissionConversionService $conversion,
        protected AdmissionReadinessPolicy $readiness,
    ) {}

    public function inboxQuery(array $filters = []): Builder
    {
        $query = AdmissionApplication::query()
            ->with([
                'primaryApplicant.targetCategory',
                'primaryContact',
                'latestVisit',
                'assignedTo:id,name,email',
                'targetCategory',
            ]);

        if ($stage = $filters['stage'] ?? null) {
            $query->where('pipeline_stage', $stage);
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($academicYear = $filters['academic_year'] ?? null) {
            $query->where('academic_year', $academicYear);
        }

        if ($assignedTo = $filters['assigned_to'] ?? null) {
            $query->where('assigned_to_user_id', (int) $assignedTo);
        }

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $like = '%'.$search.'%';
            $query->where(function (Builder $q) use ($like, $search) {
                $q->where('reference_code', 'like', $like)
                    ->orWhereHas('applicants', function (Builder $a) use ($like) {
                        $a->where('first_name', 'like', $like)
                            ->orWhere('father_name', 'like', $like)
                            ->orWhere('grandfather_name', 'like', $like);
                    })
                    ->orWhereHas('contacts', function (Builder $c) use ($like) {
                        $c->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('phone', 'like', $like);
                    });

                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        return $query->orderByDesc('created_at');
    }

    public function paginatedInbox(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->inboxQuery($filters)
            ->paginate($perPage)
            ->through(fn (AdmissionApplication $app) => $this->inboxRow($app));
    }

    public function inboxRows(array $filters = []): array
    {
        return $this->inboxQuery($filters)
            ->get()
            ->map(fn (AdmissionApplication $app) => $this->inboxRow($app))
            ->all();
    }

    public function inboxRow(AdmissionApplication $application): array
    {
        $applicant = $application->primaryApplicant;
        $contact = $application->primaryContact;
        $visit = $application->latestVisit;
        $targetCategory = $this->resolvedTargetCategory($application, $applicant);

        return [
            'id' => $application->id,
            'reference_code' => $application->reference_code,
            'parent_name' => $contact?->name,
            'student_name' => $applicant?->displayName() ?: $applicant?->first_name,
            'target_grade' => $targetCategory['name'] ?? null,
            'visit_date' => $visit?->scheduled_date?->format('Y-m-d'),
            'visit_time' => $visit?->scheduled_time,
            'pipeline_stage' => $application->pipeline_stage,
            'pipeline_stage_label' => AdmissionStage::label($application->pipeline_stage),
            'assigned_to' => $application->assignedTo ? [
                'id' => $application->assignedTo->id,
                'name' => $application->assignedTo->name,
            ] : null,
            'status' => $application->status,
            'status_label' => AdmissionStatus::label($application->status),
            'decision' => $application->decision,
            'decision_label' => AdmissionDecision::label($application->decision),
            'academic_year' => $application->academic_year,
            'source_channel' => $application->source_channel,
            'created_at' => $application->created_at?->toIso8601String(),
        ];
    }

    public function forWorkspaceHub(AdmissionApplication $application): array
    {
        $this->documents->ensureChecklist($application);

        $application->load([
            'applicants.targetCategory',
            'contacts',
            'visits',
            'documents.reviewedBy:id,name',
            'internalNotes.author:id,name',
            'stageHistories.performedBy:id,name',
            'assignmentHistories.fromUser:id,name',
            'assignmentHistories.toUser:id,name',
            'assignmentHistories.performedBy:id,name',
            'assignedTo:id,name,email',
            'targetCategory',
            'decisionBy:id,name',
            'convertedBy:id,name',
            'convertedStudent:id,name,student_code',
            'convertedStudent.guardians:id,name',
            'decisionHistories.performedBy:id,name',
        ]);

        $primaryApplicant = $application->applicants->first();
        $primaryContact = $application->contacts->firstWhere('is_primary', true)
            ?? $application->contacts->first();
        $lastActivity = $this->resolveLastActivity($application);
        $targetCategory = $this->resolvedTargetCategory($application, $primaryApplicant);

        return [
            'application' => [
                'id' => $application->id,
                'reference_code' => $application->reference_code,
                'pipeline_stage' => $application->pipeline_stage,
                'pipeline_stage_label' => AdmissionStage::label($application->pipeline_stage),
                'status' => $application->status,
                'status_label' => AdmissionStatus::label($application->status),
                'academic_year' => $application->academic_year,
                'source_channel' => $application->source_channel,
                'source_channel_label' => config('admissions.source_channels.'.$application->source_channel.'.label_ar')
                    ?? $application->source_channel,
                'source_reference' => $application->source_reference,
                'priority' => $application->priority,
                'notes' => $application->notes,
                'assigned_to' => $application->assignedTo ? [
                    'id' => $application->assignedTo->id,
                    'name' => $application->assignedTo->name,
                    'email' => $application->assignedTo->email,
                ] : null,
                'target_category' => $targetCategory,
                'current_grade_label' => $primaryApplicant?->current_grade_label,
                'target_grade' => $targetCategory['name'] ?? null,
                'created_at' => $application->created_at?->toIso8601String(),
                'updated_at' => $application->updated_at?->toIso8601String(),
                'last_activity_at' => $lastActivity,
                'decision' => $application->decision,
                'decision_label' => AdmissionDecision::label($application->decision),
                'decision_at' => $application->decision_at?->toIso8601String(),
                'decision_by' => $application->decisionBy ? [
                    'id' => $application->decisionBy->id,
                    'name' => $application->decisionBy->name,
                ] : null,
                'converted_student' => $application->convertedStudent ? [
                    'id' => $application->convertedStudent->id,
                    'name' => $application->convertedStudent->name,
                    'student_code' => $application->convertedStudent->student_code,
                    'profile_url' => route('admin.students.show', $application->convertedStudent),
                ] : null,
                'converted_guardian' => $this->resolvedConvertedGuardian($application, $primaryContact),
                'converted_at' => $application->converted_at?->toIso8601String(),
                'converted_by' => $application->convertedBy?->name,
                'is_read_only' => $application->isReadOnly(),
            ],
            'overview' => [
                'reference_code' => $application->reference_code,
                'pipeline_stage_label' => AdmissionStage::label($application->pipeline_stage),
                'status_label' => AdmissionStatus::label($application->status),
                'assigned_officer' => $application->assignedTo?->name,
                'academic_year' => $application->academic_year,
                'current_grade_label' => $primaryApplicant?->current_grade_label,
                'target_grade' => $targetCategory['name'] ?? null,
                'target_category' => $targetCategory,
                'source_channel_label' => config('admissions.source_channels.'.$application->source_channel.'.label_ar')
                    ?? $application->source_channel,
                'created_at' => $application->created_at?->toIso8601String(),
                'last_activity_at' => $lastActivity,
                'document_summary' => $this->documents->summaryFor($application),
                'decision' => [
                    'current' => $application->decision,
                    'current_label' => AdmissionDecision::label($application->decision),
                    'decision_at' => $application->decision_at?->toIso8601String(),
                    'decision_by' => $application->decisionBy?->name,
                    'conversion_status' => $application->converted_student_id ? 'converted' : ($application->decision ? 'decided' : 'pending'),
                    'conversion_status_label' => $application->converted_student_id
                        ? 'تم التحويل'
                        : ($application->decision ? 'تم اتخاذ قرار' : 'بانتظار القرار'),
                    'converted_student' => $application->convertedStudent ? [
                        'id' => $application->convertedStudent->id,
                        'name' => $application->convertedStudent->name,
                        'student_code' => $application->convertedStudent->student_code,
                        'profile_url' => route('admin.students.show', $application->convertedStudent),
                    ] : null,
                    'converted_guardian' => $this->resolvedConvertedGuardian($application, $primaryContact),
                    'converted_at' => $application->converted_at?->toIso8601String(),
                    'converted_by' => $application->convertedBy?->name,
                ],
            ],
            'applicants' => $application->applicants->map(fn ($a) => [
                'id' => $a->id,
                'display_name' => $a->displayName(),
                'first_name' => $a->first_name,
                'father_name' => $a->father_name,
                'grandfather_name' => $a->grandfather_name,
                'current_grade_label' => $a->current_grade_label,
                'target_stage_label' => $a->target_stage_label,
                'target_category_id' => $a->target_category_id ?? $application->target_category_id,
                'target_category' => $this->categoryPayload($a->target_category_id ?? $application->target_category_id),
                'date_of_birth' => $a->date_of_birth?->format('Y-m-d'),
                'gender' => $a->gender,
                'national_id' => $a->national_id,
                'notes' => $a->notes,
            ])->values(),
            'contacts' => $application->contacts->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'phone' => $c->phone,
                'national_id' => $c->national_id,
                'address' => $c->address,
                'communication_preferences' => $c->communication_preferences ?? [
                    'email' => true,
                    'phone' => true,
                    'sms' => false,
                    'whatsapp' => false,
                ],
                'relationship_type' => $c->relationship_type,
                'is_primary' => $c->is_primary,
                'matched_guardian_id' => $c->matched_guardian_id,
                'matched_guardian' => $this->matchedGuardianPayload($c->matched_guardian_id),
            ])->values(),
            'visits' => $application->visits->map(fn ($v) => [
                'id' => $v->id,
                'scheduled_date' => $v->scheduled_date?->format('Y-m-d'),
                'scheduled_time' => $v->scheduled_time,
                'status' => $v->status,
                'outcome' => $v->outcome,
                'attendance_status' => $v->attendance_status,
                'notes' => $v->notes,
                'follow_up_notes' => $v->follow_up_notes,
                'completed_at' => $v->completed_at?->toIso8601String(),
            ])->values(),
            'documents' => $application->documents->map(fn ($d) => [
                'id' => $d->id,
                'document_key' => $d->document_key,
                'label' => $d->label,
                'required' => $d->required,
                'status' => $d->status,
                'status_label' => \App\Support\Admission\AdmissionDocumentStatus::label($d->status),
                'parent_communication' => $this->documents->parentCommunicationPayload($d),
                'file_path' => $d->file_path,
                'original_filename' => $d->original_filename,
                'mime_type' => $d->mime_type,
                'file_size' => $d->file_size,
                'reviewed_by' => $d->reviewedBy?->name,
                'reviewed_at' => $d->reviewed_at?->toIso8601String(),
                'notes' => $d->notes,
            ])->values(),
            'notes' => $application->internalNotes
                ->sortByDesc('created_at')
                ->values()
                ->map(fn ($n) => [
                    'id' => $n->id,
                    'content' => $n->content,
                    'visibility' => $n->visibility,
                    'visibility_label' => config('admissions.note_visibilities.'.$n->visibility.'.label_ar') ?? $n->visibility,
                    'author' => $n->author?->name,
                    'created_at' => $n->created_at?->toIso8601String(),
                ]),
            'assignment_histories' => $application->assignmentHistories
                ->sortByDesc('effective_at')
                ->values()
                ->map(fn ($h) => [
                    'id' => $h->id,
                    'from_user' => $h->fromUser?->name ?? '—',
                    'to_user' => $h->toUser?->name ?? '—',
                    'notes' => $h->notes,
                    'performed_by' => $h->performedBy?->name,
                    'effective_at' => $h->effective_at?->toIso8601String(),
                ]),
            'stage_histories' => $application->stageHistories
                ->sortByDesc('effective_at')
                ->values()
                ->map(fn ($h) => [
                    'id' => $h->id,
                    'from_stage' => $h->from_stage,
                    'from_stage_label' => $h->from_stage ? AdmissionStage::label($h->from_stage) : null,
                    'to_stage' => $h->to_stage,
                    'to_stage_label' => AdmissionStage::label($h->to_stage),
                    'reason' => $h->reason,
                    'notes' => $h->notes,
                    'performed_by' => $h->performedBy?->name,
                    'effective_at' => $h->effective_at?->toIso8601String(),
                ]),
            'timeline' => $this->timeline->build($application),
            'engagement_timeline' => $this->engagements->timeline($application),
            'duplicate_analysis' => $this->duplicates->analyze($application),
            'guardian_suggestions' => $this->matcher->suggestMatches($application),
            'pipeline' => [
                'current_stage' => $application->pipeline_stage,
                'stage_options' => AdmissionStage::forwardOptions($application->pipeline_stage),
            ],
            'decision_histories' => $application->decisionHistories
                ->sortByDesc('effective_at')
                ->values()
                ->map(fn ($h) => [
                    'id' => $h->id,
                    'from_decision' => $h->from_decision,
                    'from_decision_label' => AdmissionDecision::label($h->from_decision),
                    'to_decision' => $h->to_decision,
                    'to_decision_label' => AdmissionDecision::label($h->to_decision),
                    'reason' => $h->reason,
                    'notes' => $h->to_decision === AdmissionDecision::CONVERTED ? null : $h->notes,
                    'performed_by' => $h->performedBy?->name,
                    'effective_at' => $h->effective_at?->toIso8601String(),
                ]),
            'visit_readiness' => $this->cachedReadiness($application, AdmissionReadinessPolicy::CONTEXT_VISIT_SCHEDULE),
            'lead_readiness' => $this->cachedReadiness($application, AdmissionReadinessPolicy::CONTEXT_LEAD),
            'application_readiness' => $this->cachedReadiness($application, AdmissionReadinessPolicy::CONTEXT_APPLICATION),
            'decision_readiness' => $this->cachedReadiness($application, AdmissionReadinessPolicy::CONTEXT_DECISION),
            'conversion_readiness' => array_merge(
                $this->cachedReadiness($application, AdmissionReadinessPolicy::CONTEXT_CONVERSION),
                ['target_category' => $targetCategory],
            ),
            'quick_actions' => $this->quickActions($application),
        ];
    }

    protected function resolvedConvertedGuardian(AdmissionApplication $application, $primaryContact): ?array
    {
        $guardianId = $primaryContact?->matched_guardian_id;

        if (! $guardianId && $application->convertedStudent) {
            $guardian = $application->convertedStudent->guardians->first();
            $guardianId = $guardian?->id;
        }

        if (! $guardianId) {
            return null;
        }

        $guardian = User::query()->find($guardianId);

        if (! $guardian) {
            return null;
        }

        return [
            'id' => $guardian->id,
            'name' => $guardian->name,
            'profile_url' => route('admin.parents.show', $guardian),
        ];
    }

    /** @var array<string, array<string, mixed>> */
    protected array $readinessCache = [];

    protected function cachedReadiness(AdmissionApplication $application, string $context): array
    {
        if (! isset($this->readinessCache[$context])) {
            $this->readinessCache[$context] = $this->readiness
                ->evaluate($application, $context)
                ->toArray();
        }

        return $this->readinessCache[$context];
    }

    protected function matchedGuardianPayload(?int $guardianId): ?array
    {
        if (! $guardianId) {
            return null;
        }

        $guardian = User::query()->find($guardianId);

        if (! $guardian) {
            return null;
        }

        return [
            'id' => $guardian->id,
            'name' => $guardian->name,
            'profile_url' => route('admin.parents.show', $guardian),
        ];
    }

    protected function quickActions(AdmissionApplication $application): array
    {
        $readOnly = $application->isReadOnly();
        $conversionReady = $this->cachedReadiness($application, AdmissionReadinessPolicy::CONTEXT_CONVERSION)['ready'] ?? false;
        $decisionReady = $this->cachedReadiness($application, AdmissionReadinessPolicy::CONTEXT_DECISION)['ready'] ?? false;
        $actor = Auth::user();
        $can = static fn (string $permission): bool => $actor instanceof User && $actor->hasAdminPermission($permission);

        return [
            'accept' => $can('admissions.accept')
                && ! $readOnly
                && $application->decision !== AdmissionDecision::ACCEPTED
                && $decisionReady,
            'reject' => $can('admissions.reject')
                && ! $readOnly
                && $application->decision !== AdmissionDecision::REJECTED,
            'waitlist' => $can('admissions.waitlist')
                && ! $readOnly
                && $application->decision !== AdmissionDecision::WAITLISTED,
            'withdraw' => $can('admissions.withdraw')
                && ! $readOnly
                && $application->decision !== AdmissionDecision::WITHDRAWN,
            'convert' => $can('admissions.convert')
                && ! $readOnly
                && $conversionReady,
        ];
    }

    /** @deprecated Use forWorkspaceHub */
    public function forAdminShow(AdmissionApplication $application): array
    {
        return $this->forWorkspaceHub($application);
    }

    public function filterOptions(): array
    {
        return [
            'stages' => AdmissionStage::options(),
            'statuses' => AdmissionStatus::options(),
            'academic_years' => AdmissionApplication::query()
                ->distinct()
                ->orderByDesc('academic_year')
                ->pluck('academic_year')
                ->values(),
            'officers' => User::query()
                ->where('user_type', 'admin')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])
                ->values(),
            'document_statuses' => collect(config('admissions.document_statuses', []))
                ->map(fn ($labels, $value) => [
                    'value' => $value,
                    'label' => $labels['label_ar'] ?? $value,
                ])
                ->values(),
            'visit_statuses' => collect(config('admissions.visit_statuses', []))
                ->map(fn ($labels, $value) => [
                    'value' => $value,
                    'label' => $labels['label_ar'] ?? $value,
                ])
                ->values(),
            'visit_outcomes' => collect(config('admissions.visit_outcomes', []))
                ->map(fn ($labels, $value) => [
                    'value' => $value,
                    'label' => $labels['label_ar'] ?? $value,
                ])
                ->values(),
            'visit_attendance_statuses' => collect(config('admissions.visit_attendance_statuses', []))
                ->map(fn ($labels, $value) => [
                    'value' => $value,
                    'label' => $labels['label_ar'] ?? $value,
                ])
                ->values(),
            'decisions' => AdmissionDecision::options(),
            'note_visibilities' => collect(config('admissions.note_visibilities', []))
                ->map(fn ($labels, $value) => [
                    'value' => $value,
                    'label' => $labels['label_ar'] ?? $value,
                ])
                ->values(),
        ];
    }

    protected function categoryPayload(?int $categoryId): ?array
    {
        if (! $categoryId) {
            return null;
        }

        $category = Category::query()->find($categoryId);

        return $category
            ? ['id' => $category->id, 'name' => $category->name]
            : ['id' => $categoryId, 'name' => null];
    }

    protected function resolvedTargetCategory(AdmissionApplication $application, $primaryApplicant): ?array
    {
        $categoryId = $application->target_category_id ?? $primaryApplicant?->target_category_id;

        return $this->categoryPayload($categoryId);
    }

    protected function resolveLastActivity(AdmissionApplication $application): ?string
    {
        $candidates = collect([
            $application->updated_at,
            $application->decision_at,
            $application->converted_at,
            $application->stageHistories->max('effective_at'),
            $application->decisionHistories->max('effective_at'),
            $application->internalNotes->max('created_at'),
            $application->assignmentHistories->max('effective_at'),
            $application->visits->max('updated_at'),
        ])->filter();

        $latest = $candidates->max();

        return $latest?->toIso8601String();
    }
}
