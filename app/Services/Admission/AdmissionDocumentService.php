<?php

namespace App\Services\Admission;

use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionDocument;
use App\Models\Admission\AdmissionDocumentHistory;
use App\Services\PlatformAuditService;
use App\Support\Admission\AdmissionDocumentStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdmissionDocumentService
{
    public function __construct(
        protected PlatformAuditService $audit,
        protected AdmissionDocumentDefinitionService $definitions,
    ) {}

    public function ensureChecklist(AdmissionApplication $application): void
    {
        foreach ($this->definitions->activeDefinitions() as $definition) {
            $document = AdmissionDocument::firstOrCreate(
                [
                    'admission_application_id' => $application->id,
                    'document_key' => $definition->key,
                ],
                [
                    'label' => $definition->label_ar,
                    'required' => $definition->required,
                    'status' => AdmissionDocumentStatus::NEEDS_UPLOAD,
                ],
            );

            if ($document->label !== $definition->label_ar || $document->required !== $definition->required) {
                $document->forceFill([
                    'label' => $definition->label_ar,
                    'required' => $definition->required,
                ])->save();
            }
        }
    }

    public function updateStatus(
        AdmissionDocument $document,
        string $toStatus,
        ?string $notes = null,
        ?string $filePath = null,
    ): AdmissionDocument {
        if ($toStatus === AdmissionDocumentStatus::REUPLOAD_REQUIRED && blank($notes)) {
            throw ValidationException::withMessages([
                'notes' => 'سبب إعادة الرفع مطلوب.',
            ]);
        }

        if ($document->status === $toStatus && $filePath === null && $notes === null) {
            return $document;
        }

        return $this->persistDocumentStatus($document, $toStatus, $notes, $filePath);
    }

    public function review(AdmissionDocument $document, string $action, ?string $notes = null): AdmissionDocument
    {
        if (! in_array($document->status, AdmissionDocumentStatus::reviewable(), true)) {
            throw ValidationException::withMessages([
                'action' => 'يمكن مراجعة المستندات المقدّمة أو قيد المراجعة فقط.',
            ]);
        }

        $toStatus = match ($action) {
            'approve' => AdmissionDocumentStatus::APPROVED,
            'reupload' => AdmissionDocumentStatus::REUPLOAD_REQUIRED,
            'reject' => AdmissionDocumentStatus::REJECTED,
            default => throw ValidationException::withMessages(['action' => 'إجراء مراجعة غير صالح.']),
        };

        if ($action === 'reupload' && blank($notes)) {
            throw ValidationException::withMessages([
                'reupload_reason' => 'سبب إعادة الرفع مطلوب.',
            ]);
        }

        if ($action === 'reject' && blank($notes)) {
            throw ValidationException::withMessages([
                'reject_reason' => 'سبب الرفض مطلوب.',
            ]);
        }

        $before = $this->auditSnapshot($document);

        $updated = $this->persistDocumentStatus($document, $toStatus, $notes);

        $auditAction = match ($action) {
            'approve' => 'document_approved',
            'reupload' => 'document_reupload_required',
            'reject' => 'document_rejected',
        };

        $document->loadMissing('application');

        $this->audit->record(
            'admissions',
            $auditAction,
            $document->application,
            $before,
            $this->auditSnapshot($updated),
            [
                'document_id' => $document->id,
                'document_key' => $document->document_key,
                'document_label' => $document->label,
                'notes' => $notes,
            ],
        );

        return $updated;
    }

    public function summaryFor(AdmissionApplication $application): array
    {
        if (! $application->relationLoaded('documents')) {
            $application->load('documents');
        }

        $documents = $application->documents;
        $required = $documents->where('required', true);
        $progressPool = $required->count() > 0 ? $required : $documents;
        $requiredApproved = $required->where('status', AdmissionDocumentStatus::APPROVED)->count();
        $requiredTotal = $required->count();
        $requiredIncomplete = max(0, $requiredTotal - $requiredApproved);
        $progressApproved = $progressPool->where('status', AdmissionDocumentStatus::APPROVED)->count();
        $progressTotal = $progressPool->count();
        $progressPercent = $progressTotal > 0
            ? (int) round(($progressApproved / $progressTotal) * 100)
            : 100;
        $pendingReviewStatuses = AdmissionDocumentStatus::reviewable();

        return [
            'total' => $documents->count(),
            'needs_upload' => $documents->where('status', AdmissionDocumentStatus::NEEDS_UPLOAD)->count(),
            'review_pending' => $documents->whereIn('status', $pendingReviewStatuses)->count(),
            'approved' => $documents->where('status', AdmissionDocumentStatus::APPROVED)->count(),
            'reupload_required' => $documents->where('status', AdmissionDocumentStatus::REUPLOAD_REQUIRED)->count(),
            'rejected' => $documents->where('status', AdmissionDocumentStatus::REJECTED)->count(),
            'required_total' => $requiredTotal,
            'required_approved' => $requiredApproved,
            'required_incomplete' => $requiredIncomplete,
            'required_pending' => $requiredIncomplete,
            'required_needs_upload' => $required->where('status', AdmissionDocumentStatus::NEEDS_UPLOAD)->count(),
            'required_review_pending' => $required->whereIn('status', $pendingReviewStatuses)->count(),
            'required_pending_review' => $required->whereIn('status', $pendingReviewStatuses)->count(),
            'required_reupload_required' => $required->where('status', AdmissionDocumentStatus::REUPLOAD_REQUIRED)->count(),
            'required_rejected' => $required->where('status', AdmissionDocumentStatus::REJECTED)->count(),
            'progress_total' => $progressTotal,
            'progress_approved' => $progressApproved,
            'progress_percent' => $progressPercent,
            'missing' => 0,
            'complete' => $requiredTotal === 0 || $requiredApproved === $requiredTotal,
        ];
    }

    /**
     * @return array{label: string, detail: string|null}
     */
    public function readinessPresentation(AdmissionApplication $application, string $blockingContext = 'decision'): array
    {
        $summary = $this->summaryFor($application);
        $approved = (int) ($summary['required_approved'] ?? 0);
        $total = (int) ($summary['required_total'] ?? 0);
        $blocking = (int) ($summary['required_incomplete'] ?? 0);
        $complete = (bool) ($summary['complete'] ?? false);

        if ($total === 0) {
            return [
                'label' => 'المستندات مكتملة',
                'detail' => null,
            ];
        }

        $label = "المستندات: {$approved} / {$total} معتمد";

        if ($complete) {
            return ['label' => $label, 'detail' => null];
        }

        $verb = match ($blockingContext) {
            'conversion' => 'التحويل',
            default => 'اتخاذ القرار',
        };

        return [
            'label' => $label,
            'detail' => "{$blocking} مستندات تمنع {$verb}",
        ];
    }

    public function uploadFile(AdmissionDocument $document, UploadedFile $file): AdmissionDocument
    {
        $this->validateUpload($file);

        if ($document->file_path) {
            $this->deleteStoredFile($document->file_path);
        }

        $path = $file->storeAs(
            config('admissions_intake.documents.path_prefix').'/'.$document->admission_application_id,
            $document->document_key.'-'.Str::uuid().'.'.$file->getClientOriginalExtension(),
            config('admissions_intake.documents.disk', 'local'),
        );

        return $this->persistDocumentStatus(
            $document,
            AdmissionDocumentStatus::REVIEW_PENDING,
            null,
            $path,
            $file->getClientOriginalName(),
            $file->getMimeType(),
            $file->getSize(),
            recordReviewMeta: false,
        );
    }

    public function removeFile(AdmissionDocument $document): AdmissionDocument
    {
        if ($document->file_path) {
            $this->deleteStoredFile($document->file_path);
        }

        $fromStatus = $document->status;

        $document->forceFill([
            'file_path' => null,
            'original_filename' => null,
            'mime_type' => null,
            'file_size' => null,
            'status' => AdmissionDocumentStatus::NEEDS_UPLOAD,
        ])->save();

        AdmissionDocumentHistory::create([
            'admission_document_id' => $document->id,
            'admission_application_id' => $document->admission_application_id,
            'from_status' => $fromStatus,
            'to_status' => AdmissionDocumentStatus::NEEDS_UPLOAD,
            'notes' => null,
            'performed_by_user_id' => Auth::id(),
            'effective_at' => now(),
        ]);

        return $document->fresh();
    }

    public function downloadPath(AdmissionDocument $document): ?string
    {
        if (! $document->file_path) {
            return null;
        }

        $disk = config('admissions_intake.documents.disk', 'local');

        if (! Storage::disk($disk)->exists($document->file_path)) {
            return null;
        }

        return Storage::disk($disk)->path($document->file_path);
    }

    /**
     * @return array<string, mixed>
     */
    public function parentCommunicationPayload(AdmissionDocument $document): array
    {
        $showNote = in_array($document->status, [
            AdmissionDocumentStatus::REUPLOAD_REQUIRED,
            AdmissionDocumentStatus::REJECTED,
        ], true);

        return [
            'document_label' => $document->label,
            'status' => $document->status,
            'status_label' => AdmissionDocumentStatus::label($document->status),
            'admin_note' => $showNote ? $document->notes : null,
        ];
    }

    protected function validateUpload(UploadedFile $file): void
    {
        $maxKb = (int) config('admissions_intake.documents.max_size_kb', 10240);
        $allowedMimes = config('admissions_intake.documents.allowed_mimes', []);
        $allowedExt = config('admissions_intake.documents.allowed_extensions', []);

        if ($file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages([
                'file' => ["File exceeds maximum size of {$maxKb}KB."],
            ]);
        }

        if (! in_array($file->getMimeType(), $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'file' => ['File type not allowed.'],
            ]);
        }

        if (! in_array(strtolower($file->getClientOriginalExtension()), $allowedExt, true)) {
            throw ValidationException::withMessages([
                'file' => ['File extension not allowed.'],
            ]);
        }
    }

    protected function deleteStoredFile(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk(config('admissions_intake.documents.disk', 'local'))->delete($path);
    }

    /**
     * @return array<string, mixed>
     */
    protected function auditSnapshot(AdmissionDocument $document): array
    {
        return [
            'id' => $document->id,
            'document_key' => $document->document_key,
            'label' => $document->label,
            'status' => $document->status,
            'notes' => $document->notes,
            'file_path' => $document->file_path ? true : false,
        ];
    }

    protected function persistDocumentStatus(
        AdmissionDocument $document,
        string $toStatus,
        ?string $notes = null,
        ?string $filePath = null,
        ?string $originalFilename = null,
        ?string $mimeType = null,
        ?int $fileSize = null,
        bool $recordReviewMeta = true,
    ): AdmissionDocument {
        $allowed = array_keys(config('admissions.document_statuses', []));

        if (! in_array($toStatus, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid document status: {$toStatus}");
        }

        return DB::transaction(function () use (
            $document,
            $toStatus,
            $notes,
            $filePath,
            $originalFilename,
            $mimeType,
            $fileSize,
            $recordReviewMeta,
        ) {
            $fromStatus = $document->status;

            $fill = [
                'status' => $toStatus,
                'notes' => $notes ?? $document->notes,
                'file_path' => $filePath ?? $document->file_path,
                'original_filename' => $originalFilename ?? $document->original_filename,
                'mime_type' => $mimeType ?? $document->mime_type,
                'file_size' => $fileSize ?? $document->file_size,
            ];

            if ($recordReviewMeta) {
                $fill['reviewed_by_user_id'] = Auth::id();
                $fill['reviewed_at'] = now();
            }

            $document->forceFill($fill)->save();

            AdmissionDocumentHistory::create([
                'admission_document_id' => $document->id,
                'admission_application_id' => $document->admission_application_id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'notes' => $notes,
                'performed_by_user_id' => Auth::id(),
                'effective_at' => now(),
            ]);

            return $document->fresh();
        });
    }
}
