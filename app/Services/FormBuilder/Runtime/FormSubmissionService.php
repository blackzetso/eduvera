<?php

namespace App\Services\FormBuilder\Runtime;

use App\Events\FormBuilder\FormSubmissionFinalized;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Support\FormBuilder\FormSubmissionFinalizedPayload;
use App\Support\FormBuilder\FormFieldDefinition;
use App\Support\FormBuilder\FormLogicEffects;
use App\Support\FormBuilder\FormRuntimeFieldTypes;
use App\Support\FormBuilder\FormRuntimeContext;
use App\Support\FormBuilder\FormRuntimePayload;
use App\Support\FormBuilder\FormSubmissionException;
use App\Support\FormBuilder\FormSubmissionRequest;
use App\Support\FormBuilder\FormSubmissionResult;
use App\Support\FormBuilder\FormSubmissionSnapshot;
use App\Support\FormBuilder\FormSubmissionStatus;
use App\Support\FormBuilder\FormSubmissionTimelineEntry;
use App\Support\FormBuilder\FormValidationResult;
use Illuminate\Support\Facades\DB;

class FormSubmissionService
{
    public function __construct(
        protected FormLogicEvaluator $logicEvaluator,
        protected FormValidationService $validationService,
    ) {}

    public function submit(
        Form $form,
        FormRuntimePayload $runtime,
        FormSubmissionRequest $request,
        FormRuntimeContext $context,
    ): FormSubmissionResult {
        $this->assertSubmissionAllowed($form, $runtime, $request, $context);

        $values = $this->extractValues($request->data);
        $effects = $this->evaluateLogic($runtime, $values);
        $validation = $this->validateSubmission($runtime, $values, $effects, $request);

        if (! $validation->valid) {
            throw FormSubmissionException::validationFailed($validation);
        }

        $snapshot = FormSubmissionSnapshot::fromRuntime($runtime);
        $normalized = $this->normalizeData($runtime, $values, $effects);
        $payload = FormSubmissionSnapshot::attach($normalized, $snapshot, [
            'locale' => $request->resolvedLocale(),
            'channel' => $context->channel,
        ]);

        $status = $this->resolveInitialStatus($runtime, $request);
        $workflowStage = $this->resolveWorkflowStage($runtime, $status);

        return DB::transaction(function () use (
            $form,
            $request,
            $context,
            $payload,
            $status,
            $workflowStage,
            $validation,
            $effects,
        ) {
            $submission = $this->resolveSubmissionModel($form, $request);
            $fromStatus = $submission?->status;

            if ($submission) {
                $submission = $this->updateSubmission(
                    $submission,
                    $payload,
                    $status,
                    $workflowStage,
                    $request,
                    $context,
                    $fromStatus,
                );
            } else {
                $submission = $this->createSubmission(
                    $form,
                    $payload,
                    $status,
                    $workflowStage,
                    $request,
                    $context,
                );
                $fromStatus = null;
            }

            $this->dispatchFinalizedEvent($submission, $context, $fromStatus ?? null);

            return new FormSubmissionResult($submission, $validation, $effects);
        });
    }

    public function transitionStatus(
        FormSubmission $submission,
        string $toStatus,
        FormRuntimeContext $context,
        ?string $comment = null,
    ): FormSubmission {
        if (! FormSubmissionStatus::isValid($toStatus)) {
            throw FormSubmissionException::invalidStatus($toStatus);
        }

        $fromStatus = (string) $submission->status;

        if (! FormSubmissionStatus::canTransition($fromStatus, $toStatus)) {
            throw FormSubmissionException::invalidTransition($fromStatus, $toStatus);
        }

        $timeline = $submission->timeline ?? [];
        $timeline[] = FormSubmissionTimelineEntry::make(
            event: 'status_changed',
            fromStatus: $fromStatus,
            toStatus: $toStatus,
            workflowStage: $submission->workflow_stage,
            context: $context,
            comment: $comment,
        );

        $submission->update([
            'status' => $toStatus,
            'timeline' => $timeline,
        ]);

        $fresh = $submission->fresh();

        $this->dispatchFinalizedEvent($fresh, $context, $fromStatus);

        return $fresh;
    }

    public function findForForm(Form $form, int $submissionId): FormSubmission
    {
        $submission = FormSubmission::query()
            ->where('form_id', $form->id)
            ->whereKey($submissionId)
            ->first();

        if (! $submission) {
            throw FormSubmissionException::notFound();
        }

        return $submission;
    }

    protected function assertSubmissionAllowed(
        Form $form,
        FormRuntimePayload $runtime,
        FormSubmissionRequest $request,
        FormRuntimeContext $context,
    ): void {
        if ($runtime->formId() !== (int) $form->id) {
            throw FormSubmissionException::notAllowed('Runtime payload does not belong to this form.');
        }

        if (! FormSubmissionStatus::isValid($request->targetStatus)) {
            throw FormSubmissionException::invalidStatus($request->targetStatus);
        }

        if ($request->isDraft() && ! $runtime->allowsDraft()) {
            throw FormSubmissionException::notAllowed('Draft submissions are not enabled for this form.');
        }

        if ($request->snapshotHash !== null && $request->snapshotHash !== $runtime->snapshotHash()) {
            throw FormSubmissionException::snapshotMismatch(
                $runtime->snapshotHash(),
                $request->snapshotHash,
            );
        }

        $this->assertSubmissionWindow($runtime);
        $this->assertSubmissionLimit($form, $request, $context);
    }

    protected function assertSubmissionWindow(FormRuntimePayload $runtime): void
    {
        $submission = $runtime->settings['submission'] ?? [];
        $limit = $submission['limit'] ?? 'unlimited';

        if ($limit !== 'date_range') {
            return;
        }

        $today = now()->toDateString();
        $from = $submission['date_from'] ?? null;
        $to = $submission['date_to'] ?? null;

        if ($from && $today < $from) {
            throw FormSubmissionException::notAllowed('Form submissions are not open yet.');
        }

        if ($to && $today > $to) {
            throw FormSubmissionException::notAllowed('Form submissions are closed.');
        }
    }

    protected function assertSubmissionLimit(
        Form $form,
        FormSubmissionRequest $request,
        FormRuntimeContext $context,
    ): void {
        if ($request->isDraft() || $request->submissionId) {
            return;
        }

        $limit = $form->submission_settings['limit'] ?? 'unlimited';

        if ($limit !== 'once_per_user' || ! $context->userId) {
            return;
        }

        $exists = FormSubmission::query()
            ->where('form_id', $form->id)
            ->where('user_id', $context->userId)
            ->where('status', '!=', FormSubmissionStatus::DRAFT)
            ->exists();

        if ($exists) {
            throw FormSubmissionException::notAllowed('You have already submitted this form.');
        }
    }

    /**
     * @param  array<string, mixed>  $rawData
     * @return array<string, mixed>
     */
    protected function extractValues(array $rawData): array
    {
        unset($rawData['_meta']);

        return $rawData;
    }

    protected function evaluateLogic(FormRuntimePayload $runtime, array $values): FormLogicEffects
    {
        return $this->logicEvaluator->evaluate(
            $runtime->logicRules,
            $values,
            $runtime->sectionFieldIndex(),
            $runtime->allFieldKeys(),
        );
    }

    protected function validateSubmission(
        FormRuntimePayload $runtime,
        array $values,
        FormLogicEffects $effects,
        FormSubmissionRequest $request,
    ): FormValidationResult {
        if ($request->isDraft()) {
            return FormValidationResult::pass();
        }

        $fields = $this->filterSubmittableFields($runtime->fields(), $effects);

        return $this->validationService->validate(
            $fields,
            $values,
            $effects,
            $request->resolvedLocale(),
        );
    }

    /**
     * @param  array<int, FormFieldDefinition>  $fields
     * @return array<int, FormFieldDefinition>
     */
    protected function filterSubmittableFields(array $fields, FormLogicEffects $effects): array
    {
        return array_values(array_filter(
            $fields,
            fn (FormFieldDefinition $field) => $effects->isFieldEffective($field)
                && ! $field->readonly
                && ! $this->isUnsupportedFieldType($field->type),
        ));
    }

    protected function isUnsupportedFieldType(string $type): bool
    {
        return ! FormRuntimeFieldTypes::isSupported($type);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function normalizeData(
        FormRuntimePayload $runtime,
        array $values,
        FormLogicEffects $effects,
    ): array {
        $allowedKeys = collect($runtime->fields())
            ->filter(fn (FormFieldDefinition $field) => $effects->isFieldEffective($field)
                && ! $field->readonly
                && ! $this->isUnsupportedFieldType($field->type))
            ->map(fn (FormFieldDefinition $field) => $field->key)
            ->flip();

        return collect($values)
            ->filter(fn ($value, $key) => $allowedKeys->has($key))
            ->all();
    }

    protected function resolveInitialStatus(
        FormRuntimePayload $runtime,
        FormSubmissionRequest $request,
    ): string {
        if ($request->isDraft()) {
            return FormSubmissionStatus::DRAFT;
        }

        if ($runtime->workflowEnabled()) {
            return FormSubmissionStatus::UNDER_REVIEW;
        }

        return FormSubmissionStatus::SUBMITTED;
    }

    protected function resolveWorkflowStage(FormRuntimePayload $runtime, string $status): ?string
    {
        if ($status !== FormSubmissionStatus::UNDER_REVIEW || ! $runtime->workflowEnabled()) {
            return null;
        }

        $stages = $runtime->settings['workflow']['stages'] ?? [];

        return isset($stages[0]['id']) ? (string) $stages[0]['id'] : 'stage_1';
    }

    protected function resolveSubmissionModel(Form $form, FormSubmissionRequest $request): ?FormSubmission
    {
        if (! $request->submissionId) {
            return null;
        }

        $submission = FormSubmission::query()
            ->where('form_id', $form->id)
            ->whereKey($request->submissionId)
            ->first();

        if (! $submission) {
            throw FormSubmissionException::notFound();
        }

        if ($submission->status !== FormSubmissionStatus::DRAFT) {
            throw FormSubmissionException::notAllowed('Only draft submissions can be updated.');
        }

        return $submission;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function createSubmission(
        Form $form,
        array $payload,
        string $status,
        ?string $workflowStage,
        FormSubmissionRequest $request,
        FormRuntimeContext $context,
    ): FormSubmission {
        $timeline = [
            FormSubmissionTimelineEntry::make(
                event: 'created',
                toStatus: $status,
                workflowStage: $workflowStage,
                context: $context,
            ),
        ];

        if ($status !== FormSubmissionStatus::DRAFT) {
            $timeline[] = FormSubmissionTimelineEntry::make(
                event: 'submitted',
                fromStatus: FormSubmissionStatus::DRAFT,
                toStatus: $status,
                workflowStage: $workflowStage,
                context: $context,
            );
        }

        return FormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $context->userId,
            'status' => $status,
            'workflow_stage' => $workflowStage,
            'data' => $payload,
            'timeline' => $timeline,
            'locale' => $request->resolvedLocale(),
            'ip_address' => $context->ipAddress,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function updateSubmission(
        FormSubmission $submission,
        array $payload,
        string $status,
        ?string $workflowStage,
        FormSubmissionRequest $request,
        FormRuntimeContext $context,
        ?string $fromStatus,
    ): FormSubmission {
        $timeline = $submission->timeline ?? [];

        $timeline[] = FormSubmissionTimelineEntry::make(
            event: $status === FormSubmissionStatus::DRAFT ? 'updated' : 'submitted',
            fromStatus: $fromStatus,
            toStatus: $status,
            workflowStage: $workflowStage,
            context: $context,
        );

        $submission->update([
            'status' => $status,
            'workflow_stage' => $workflowStage,
            'data' => $payload,
            'timeline' => $timeline,
            'locale' => $request->resolvedLocale(),
            'ip_address' => $context->ipAddress,
        ]);

        return $submission->fresh();
    }

    protected function dispatchFinalizedEvent(
        FormSubmission $submission,
        FormRuntimeContext $context,
        ?string $previousStatus = null,
    ): void {
        if (! FormSubmissionFinalizedPayload::shouldEmit((string) $submission->status, $previousStatus)) {
            return;
        }

        $payload = FormSubmissionFinalizedPayload::fromSubmission($submission, $context);

        $dispatch = fn () => event(new FormSubmissionFinalized($payload));

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($dispatch);
        } else {
            $dispatch();
        }
    }
}
