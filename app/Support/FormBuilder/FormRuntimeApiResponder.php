<?php

namespace App\Support\FormBuilder;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class FormRuntimeApiResponder
{
    public function runtime(FormRuntimePayload $runtime): JsonResponse
    {
        return response()->json($runtime->toArray());
    }

    public function submissionCreated(FormSubmissionResult $result, bool $isDraft): JsonResponse
    {
        $submission = $result->submission;

        return response()->json([
            'message' => $isDraft
                ? 'تم حفظ المسودة بنجاح'
                : 'تم إرسال النموذج بنجاح',
            'message_en' => $isDraft
                ? 'Draft saved successfully'
                : 'Form submitted successfully',
            'submission' => $this->submissionSummary($submission),
        ], 201);
    }

    public function submissionDetail(FormSubmission $submission): JsonResponse
    {
        return response()->json([
            'submission' => $this->submissionDetailPayload($submission),
            'runtime' => null,
        ]);
    }

    public function submissionList(Form $form, $paginator, string $locale = 'ar'): JsonResponse
    {
        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (FormSubmission $submission) => $this->listItem($submission))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'form' => [
                'id' => $form->id,
                'name' => $locale === 'en' && $form->name_en
                    ? $form->name_en
                    : $form->name,
            ],
        ]);
    }

    public function statusUpdated(FormSubmission $submission): JsonResponse
    {
        return response()->json([
            'message' => 'تم تحديث حالة الإرسال',
            'message_en' => 'Submission status updated',
            'submission' => $this->submissionSummary($submission),
        ]);
    }

    public function fromThrowable(\Throwable $exception, ?FormRuntimePayload $runtime = null): JsonResponse
    {
        if ($exception instanceof FormAccessDeniedException) {
            return $this->accessDenied($exception, $runtime);
        }

        if ($exception instanceof FormSubmissionException) {
            return $this->submissionError($exception);
        }

        if ($exception instanceof FormRenderException) {
            return $this->renderError($exception);
        }

        if ($exception instanceof ValidationException) {
            return $this->httpValidation($exception);
        }

        throw $exception;
    }

    public function notFound(string $message = 'Resource not found.'): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'message_en' => $message,
            'reason' => 'not_found',
            'errors' => [],
        ], 404);
    }

    protected function accessDenied(FormAccessDeniedException $exception, ?FormRuntimePayload $runtime): JsonResponse
    {
        $status = $exception->reason === 'snapshot_mismatch' ? 409 : 403;

        $payload = [
            'message' => $exception->getMessage(),
            'message_en' => $exception->getMessage(),
            'reason' => $exception->reason,
            'errors' => [],
        ];

        if ($exception->reason === 'snapshot_mismatch' && $runtime !== null) {
            $payload['expected_snapshot_hash'] = $runtime->snapshotHash();
        }

        return response()->json($payload, $status);
    }

    protected function submissionError(FormSubmissionException $exception): JsonResponse
    {
        if ($exception->validationResult() !== null) {
            return $this->validationFailed($exception->validationResult());
        }

        if (str_contains($exception->getMessage(), 'Form definition changed')) {
            return response()->json([
                'message' => 'Form definition has changed. Please reload the form.',
                'message_en' => 'Form definition has changed. Please reload the form.',
                'reason' => 'snapshot_mismatch',
                'errors' => [],
            ], 409);
        }

        if (str_contains($exception->getMessage(), 'Cannot transition submission')) {
            preg_match('/from (.+?) to (.+?)\./', $exception->getMessage(), $matches);

            return response()->json([
                'message' => $exception->getMessage(),
                'message_en' => $exception->getMessage(),
                'reason' => 'invalid_transition',
                'from_status' => $matches[1] ?? null,
                'to_status' => $matches[2] ?? null,
                'errors' => [],
            ], 422);
        }

        if (str_contains($exception->getMessage(), 'Invalid submission status')) {
            return response()->json([
                'message' => $exception->getMessage(),
                'message_en' => $exception->getMessage(),
                'reason' => 'invalid_status',
                'errors' => [],
            ], 422);
        }

        if ($exception->getMessage() === 'Form submission not found.') {
            return $this->notFound($exception->getMessage());
        }

        return response()->json([
            'message' => $exception->getMessage(),
            'message_en' => $exception->getMessage(),
            'reason' => 'not_allowed',
            'errors' => [],
        ], 403);
    }

    protected function renderError(FormRenderException $exception): JsonResponse
    {
        $reason = match (true) {
            str_contains($exception->getMessage(), 'disabled') => 'form_disabled',
            str_contains($exception->getMessage(), 'not published') => 'form_not_published',
            default => 'not_renderable',
        };

        return response()->json([
            'message' => $exception->getMessage(),
            'message_en' => $exception->getMessage(),
            'reason' => $reason,
            'errors' => [],
        ], 403);
    }

    protected function validationFailed(FormValidationResult $result): JsonResponse
    {
        return response()->json([
            'message' => 'Validation failed',
            'message_en' => 'Validation failed',
            'reason' => 'validation_failed',
            'errors' => collect($result->errors)
                ->map(fn (FormValidationError $error) => $error->toArray('ar'))
                ->values()
                ->all(),
        ], 422);
    }

    protected function httpValidation(ValidationException $exception): JsonResponse
    {
        return response()->json([
            'message' => 'Validation failed',
            'message_en' => 'Validation failed',
            'reason' => 'validation_failed',
            'errors' => collect($exception->errors())
                ->flatMap(fn (array $messages, string $field) => collect($messages)->map(fn (string $message) => [
                    'field_key' => $field,
                    'rule' => 'invalid',
                    'message' => $message,
                    'message_en' => $message,
                ]))
                ->values()
                ->all(),
        ], 422);
    }

    /**
     * @return array<string, mixed>
     */
    protected function submissionSummary(FormSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'form_id' => $submission->form_id,
            'status' => $submission->status,
            'workflow_stage' => $submission->workflow_stage,
            'locale' => $submission->locale,
            'created_at' => $submission->created_at?->toIso8601String(),
            'updated_at' => $submission->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function submissionDetailPayload(FormSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'form_id' => $submission->form_id,
            'status' => $submission->status,
            'workflow_stage' => $submission->workflow_stage,
            'locale' => $submission->locale,
            'data' => $submission->data,
            'timeline' => $submission->timeline ?? [],
            'submitter' => $this->submitterPayload($submission),
            'created_at' => $submission->created_at?->toIso8601String(),
            'updated_at' => $submission->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function listItem(FormSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'status' => $submission->status,
            'workflow_stage' => $submission->workflow_stage,
            'locale' => $submission->locale,
            'submitter' => $this->submitterPayload($submission),
            'summary' => $this->buildSummary($submission->data ?? []),
            'created_at' => $submission->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function submitterPayload(FormSubmission $submission): array
    {
        if ($submission->user_id === null) {
            return [
                'type' => 'anonymous',
                'label' => 'زائر',
            ];
        }

        $user = $submission->relationLoaded('user') ? $submission->user : User::find($submission->user_id);

        return [
            'type' => 'user',
            'id' => $submission->user_id,
            'label' => $user?->name ?? 'User',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function buildSummary(array $data): array
    {
        $summary = [];

        foreach ($data as $key => $value) {
            if ($key === '_meta' || $value === null || $value === '') {
                continue;
            }

            $summary[$key] = $value;

            if (count($summary) >= 3) {
                break;
            }
        }

        return $summary;
    }
}
