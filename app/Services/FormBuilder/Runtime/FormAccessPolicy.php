<?php

namespace App\Services\FormBuilder\Runtime;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\Admin\PermissionService;
use App\Support\FormBuilder\FormAccessDeniedException;
use App\Support\FormBuilder\FormRuntimeContext;
use App\Support\FormBuilder\FormRuntimePayload;
use App\Support\FormBuilder\FormSubmissionStatus;

class FormAccessPolicy
{
    public function __construct(
        protected PermissionService $permissions,
    ) {}

    public function canPreview(Form $form, FormRuntimeContext $context): bool
    {
        return $this->hasFormManagePermission($context);
    }

    public function canRender(Form $form, FormRuntimeContext $context): bool
    {
        try {
            $this->authorizeRender($form, $context);

            return true;
        } catch (FormAccessDeniedException) {
            return false;
        }
    }

    public function canSubmit(
        Form $form,
        FormRuntimeContext $context,
        ?FormRuntimePayload $runtime = null,
        ?string $providedSnapshotHash = null,
    ): bool {
        try {
            $this->authorizeSubmit($form, $context, $runtime, $providedSnapshotHash);

            return true;
        } catch (FormAccessDeniedException) {
            return false;
        }
    }

    public function canSaveDraft(
        Form $form,
        FormRuntimeContext $context,
        ?FormRuntimePayload $runtime = null,
    ): bool {
        try {
            $this->authorizeSaveDraft($form, $context, $runtime);

            return true;
        } catch (FormAccessDeniedException) {
            return false;
        }
    }

    public function canUpdateDraft(
        Form $form,
        FormRuntimeContext $context,
        FormSubmission $submission,
        ?FormRuntimePayload $runtime = null,
    ): bool {
        try {
            $this->authorizeUpdateDraft($form, $context, $submission, $runtime);

            return true;
        } catch (FormAccessDeniedException) {
            return false;
        }
    }

    public function canViewSubmission(
        Form $form,
        FormRuntimeContext $context,
        FormSubmission $submission,
    ): bool {
        try {
            $this->authorizeViewSubmission($form, $context, $submission);

            return true;
        } catch (FormAccessDeniedException) {
            return false;
        }
    }

    public function canListSubmissions(Form $form, FormRuntimeContext $context): bool
    {
        try {
            $this->authorizeListSubmissions($form, $context);

            return true;
        } catch (FormAccessDeniedException) {
            return false;
        }
    }

    public function canTransitionStatus(
        Form $form,
        FormRuntimeContext $context,
        FormSubmission $submission,
        string $toStatus,
    ): bool {
        try {
            $this->authorizeTransitionStatus($form, $context, $submission, $toStatus);

            return true;
        } catch (FormAccessDeniedException) {
            return false;
        }
    }

    public function authorizePreview(Form $form, FormRuntimeContext $context): void
    {
        if (! $this->canPreview($form, $context)) {
            throw FormAccessDeniedException::denied(
                'You are not allowed to preview this form.',
                'preview_denied',
            );
        }
    }

    public function authorizeRender(Form $form, FormRuntimeContext $context): void
    {
        if ($this->hasStaffViewAccess($context)) {
            return;
        }

        $this->assertFormOperational($form);
        $this->assertVisibilityAudience($form, $context);
    }

    public function authorizeSubmit(
        Form $form,
        FormRuntimeContext $context,
        ?FormRuntimePayload $runtime = null,
        ?string $providedSnapshotHash = null,
    ): void {
        $this->assertFormOperational($form);
        $this->assertVisibilityAudience($form, $context);
        $this->assertSubmissionWindow($form);
        $this->assertAuthenticatedForSubmissionLimit($form, $context);
        $this->assertSnapshotForFinalSubmit($runtime, $providedSnapshotHash);
    }

    public function authorizeSaveDraft(
        Form $form,
        FormRuntimeContext $context,
        ?FormRuntimePayload $runtime = null,
    ): void {
        if (! $this->draftsEnabled($form, $runtime)) {
            throw FormAccessDeniedException::denied(
                'Draft submissions are not enabled for this form.',
                'draft_disabled',
            );
        }

        $this->assertFormOperational($form);
        $this->assertVisibilityAudience($form, $context);
    }

    public function authorizeUpdateDraft(
        Form $form,
        FormRuntimeContext $context,
        FormSubmission $submission,
        ?FormRuntimePayload $runtime = null,
    ): void {
        if ($submission->form_id !== $form->id) {
            throw FormAccessDeniedException::denied(
                'Submission does not belong to this form.',
                'submission_form_mismatch',
            );
        }

        if ($submission->status !== FormSubmissionStatus::DRAFT) {
            throw FormAccessDeniedException::denied(
                'Only draft submissions can be updated.',
                'not_draft',
            );
        }

        if ($this->hasFormManagePermission($context)) {
            return;
        }

        $this->authorizeSaveDraft($form, $context, $runtime);
        $this->assertSubmissionOwnership($submission, $context);
    }

    public function authorizeViewSubmission(
        Form $form,
        FormRuntimeContext $context,
        FormSubmission $submission,
    ): void {
        if ($submission->form_id !== $form->id) {
            throw FormAccessDeniedException::denied(
                'Submission does not belong to this form.',
                'submission_form_mismatch',
            );
        }

        if ($this->hasStaffViewAccess($context)) {
            return;
        }

        $this->assertSubmissionOwnership($submission, $context);
    }

    public function authorizeListSubmissions(Form $form, FormRuntimeContext $context): void
    {
        if (! $this->hasStaffViewAccess($context)) {
            throw FormAccessDeniedException::denied(
                'You are not allowed to list submissions for this form.',
                'list_denied',
            );
        }
    }

    public function authorizeTransitionStatus(
        Form $form,
        FormRuntimeContext $context,
        FormSubmission $submission,
        string $toStatus,
    ): void {
        if ($submission->form_id !== $form->id) {
            throw FormAccessDeniedException::denied(
                'Submission does not belong to this form.',
                'submission_form_mismatch',
            );
        }

        if (! FormSubmissionStatus::isValid($toStatus)) {
            throw FormAccessDeniedException::denied(
                "Invalid submission status: {$toStatus}",
                'invalid_status',
            );
        }

        if (! $this->hasStaffReviewAccess($context)) {
            throw FormAccessDeniedException::denied(
                'You are not allowed to change submission status.',
                'transition_denied',
            );
        }
    }

    protected function assertFormOperational(Form $form): void
    {
        if ($form->status !== 'enable') {
            throw FormAccessDeniedException::denied(
                'Form is disabled.',
                'form_disabled',
            );
        }

        if (($form->publication_status ?? 'draft') !== 'published') {
            throw FormAccessDeniedException::denied(
                'Form is not published.',
                'form_not_published',
            );
        }
    }

    protected function assertVisibilityAudience(Form $form, FormRuntimeContext $context): void
    {
        $visibility = $form->visibility_settings ?? ['mode' => 'staff', 'audiences' => []];
        $mode = (string) ($visibility['mode'] ?? 'staff');
        $audiences = $visibility['audiences'] ?? [];

        if ($mode === 'public') {
            return;
        }

        if ($context->userId === null && $context->user === null) {
            throw FormAccessDeniedException::denied(
                'Authentication is required to access this form.',
                'authentication_required',
            );
        }

        $userType = $this->resolvedUserType($context);

        $requiredType = config("form-builder.visibility_user_types.$mode");

        if ($mode === 'staff') {
            if (! $this->isAdminActor($context)) {
                throw FormAccessDeniedException::denied(
                    'This form is restricted to staff users.',
                    'visibility_staff',
                );
            }

            return;
        }

        if ($mode === 'custom_roles') {
            if (! $this->isAdminActor($context)) {
                throw FormAccessDeniedException::denied(
                    'This form is restricted to authorized staff roles.',
                    'visibility_custom_roles',
                );
            }

            $role = $this->resolvedAdminRole($context);

            if ($audiences !== [] && ! in_array($role, $audiences, true)) {
                throw FormAccessDeniedException::denied(
                    'Your role is not allowed to access this form.',
                    'visibility_custom_roles',
                );
            }

            return;
        }

        if ($requiredType === null || $userType !== $requiredType) {
            throw FormAccessDeniedException::denied(
                'You are not allowed to access this form.',
                'visibility_denied',
            );
        }
    }

    protected function assertSubmissionOwnership(FormSubmission $submission, FormRuntimeContext $context): void
    {
        if ($context->userId === null) {
            throw FormAccessDeniedException::denied(
                'Authentication is required to access this submission.',
                'authentication_required',
            );
        }

        if ((int) $submission->user_id !== (int) $context->userId) {
            throw FormAccessDeniedException::denied(
                'You are not allowed to access this submission.',
                'ownership_denied',
            );
        }
    }

    protected function assertSnapshotForFinalSubmit(
        ?FormRuntimePayload $runtime,
        ?string $providedSnapshotHash,
    ): void {
        if ($runtime === null) {
            throw FormAccessDeniedException::denied(
                'Runtime payload is required for final submission.',
                'runtime_required',
            );
        }

        if ($providedSnapshotHash === null || $providedSnapshotHash === '') {
            throw FormAccessDeniedException::denied(
                'Snapshot hash is required for final submission.',
                'snapshot_required',
            );
        }

        if ($providedSnapshotHash !== $runtime->snapshotHash()) {
            throw FormAccessDeniedException::denied(
                'Form definition has changed. Please reload the form.',
                'snapshot_mismatch',
            );
        }
    }

    protected function assertSubmissionWindow(Form $form): void
    {
        $submission = $form->submission_settings ?? [];
        $limit = $submission['limit'] ?? 'unlimited';

        if ($limit !== 'date_range') {
            return;
        }

        $today = now()->toDateString();
        $from = $submission['date_from'] ?? null;
        $to = $submission['date_to'] ?? null;

        if ($from && $today < $from) {
            throw FormAccessDeniedException::denied(
                'Form submissions are not open yet.',
                'submission_window_closed',
            );
        }

        if ($to && $today > $to) {
            throw FormAccessDeniedException::denied(
                'Form submissions are closed.',
                'submission_window_closed',
            );
        }
    }

    protected function assertAuthenticatedForSubmissionLimit(Form $form, FormRuntimeContext $context): void
    {
        $limit = $form->submission_settings['limit'] ?? 'unlimited';

        if ($limit === 'once_per_user' && $context->userId === null) {
            throw FormAccessDeniedException::denied(
                'Authentication is required to submit this form.',
                'authentication_required',
            );
        }
    }

    protected function draftsEnabled(Form $form, ?FormRuntimePayload $runtime): bool
    {
        if ($runtime !== null) {
            return $runtime->allowsDraft();
        }

        return (bool) ($form->submission_settings['allow_draft'] ?? false);
    }

    protected function hasFormManagePermission(FormRuntimeContext $context): bool
    {
        $user = $context->user;

        return $user !== null
            && $user->isAdmin()
            && $this->permissions->can($user, config('form-builder.permissions.manage', 'forms.manage'));
    }

    protected function hasStaffViewAccess(FormRuntimeContext $context): bool
    {
        $user = $context->user;

        if ($user === null || ! $user->isAdmin()) {
            return false;
        }

        return $this->permissions->can($user, config('form-builder.permissions.manage', 'forms.manage'))
            || $this->permissions->can($user, config('form-builder.permissions.view_submissions', 'forms.submissions.view'));
    }

    protected function hasStaffReviewAccess(FormRuntimeContext $context): bool
    {
        $user = $context->user;

        if ($user === null || ! $user->isAdmin()) {
            return false;
        }

        return $this->permissions->can($user, config('form-builder.permissions.manage', 'forms.manage'))
            || $this->permissions->can($user, config('form-builder.permissions.review_submissions', 'forms.submissions.review'));
    }

    protected function isAdminActor(FormRuntimeContext $context): bool
    {
        if ($context->user !== null) {
            return $context->user->isAdmin();
        }

        return $context->userType === 'admin';
    }

    protected function resolvedUserType(FormRuntimeContext $context): ?string
    {
        if ($context->user !== null) {
            return $context->user->user_type;
        }

        return $context->userType;
    }

    protected function resolvedAdminRole(FormRuntimeContext $context): string
    {
        if ($context->user !== null) {
            return $this->permissions->role($context->user);
        }

        return '';
    }
}
