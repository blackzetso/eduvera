<?php

namespace Tests\Unit\FormBuilder;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Services\FormBuilder\Runtime\FormAccessPolicy;
use App\Support\FormBuilder\FormAccessDeniedException;
use App\Support\FormBuilder\FormRuntimeContext;
use App\Support\FormBuilder\FormRuntimePayload;
use App\Support\FormBuilder\FormSubmissionStatus;
use Tests\TestCase;

class FormAccessPolicyTest extends TestCase
{
    protected FormAccessPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = app(FormAccessPolicy::class);
    }

    public function test_anonymous_can_render_and_submit_public_form_with_snapshot(): void
    {
        $form = $this->makeForm(['visibility_settings' => ['mode' => 'public']]);
        $runtime = $this->makeRuntime($form);
        $context = FormRuntimeContext::anonymous('ar');

        $this->assertTrue($this->policy->canRender($form, $context));
        $this->assertTrue($this->policy->canSubmit($form, $context, $runtime, $runtime->snapshotHash()));
    }

    public function test_anonymous_cannot_access_student_form(): void
    {
        $form = $this->makeForm(['visibility_settings' => ['mode' => 'students']]);
        $context = FormRuntimeContext::anonymous('ar');

        $this->assertFalse($this->policy->canRender($form, $context));
        $this->assertFalse($this->policy->canSubmit($form, $context, $this->makeRuntime($form), 'hash'));
    }

    public function test_anonymous_final_submit_requires_snapshot_hash(): void
    {
        $form = $this->makeForm(['visibility_settings' => ['mode' => 'public']]);
        $runtime = $this->makeRuntime($form);
        $context = FormRuntimeContext::anonymous('ar');

        $this->assertFalse($this->policy->canSubmit($form, $context, $runtime, null));

        $this->expectException(FormAccessDeniedException::class);
        $this->policy->authorizeSubmit($form, $context, $runtime, null);
    }

    public function test_parent_can_access_parent_form_but_not_student_form(): void
    {
        $parentForm = $this->makeForm(['visibility_settings' => ['mode' => 'parents']]);
        $studentForm = $this->makeForm(['visibility_settings' => ['mode' => 'students']]);
        $context = $this->contextFor($this->makeUser('guardian', 11));

        $this->assertTrue($this->policy->canRender($parentForm, $context));
        $this->assertFalse($this->policy->canRender($studentForm, $context));
    }

    public function test_student_can_access_student_form(): void
    {
        $form = $this->makeForm(['visibility_settings' => ['mode' => 'students']]);
        $context = $this->contextFor($this->makeUser('student', 12));
        $runtime = $this->makeRuntime($form);

        $this->assertTrue($this->policy->canRender($form, $context));
        $this->assertTrue($this->policy->canSubmit($form, $context, $runtime, $runtime->snapshotHash()));
    }

    public function test_teacher_can_access_teacher_form(): void
    {
        $form = $this->makeForm(['visibility_settings' => ['mode' => 'teachers']]);
        $context = $this->contextFor($this->makeUser('teacher', 13));

        $this->assertTrue($this->policy->canRender($form, $context));
    }

    public function test_staff_visibility_requires_admin_actor(): void
    {
        $form = $this->makeForm(['visibility_settings' => ['mode' => 'staff']]);
        $admin = $this->makeUser('admin', 20, 'hr_officer');
        $teacher = $this->makeUser('teacher', 21);

        $this->assertTrue($this->policy->canRender($form, $this->contextFor($admin)));
        $this->assertFalse($this->policy->canRender($form, $this->contextFor($teacher)));
    }

    public function test_admin_with_manage_permission_can_preview_unpublished_form(): void
    {
        $form = $this->makeForm([
            'publication_status' => 'draft',
            'status' => 'enable',
        ]);
        $admin = $this->makeUser('admin', 30, 'super_admin');

        $this->assertTrue($this->policy->canPreview($form, $this->contextFor($admin)));
    }

    public function test_admin_staff_can_render_regardless_of_visibility(): void
    {
        $form = $this->makeForm(['visibility_settings' => ['mode' => 'staff']]);
        $admin = $this->makeUser('admin', 31, 'hr_officer');

        $this->assertTrue($this->policy->canRender($form, $this->contextFor($admin)));
        $this->assertTrue($this->policy->canListSubmissions($form, $this->contextFor($admin)));
    }

    public function test_disabled_and_unpublished_forms_are_denied_for_public_render(): void
    {
        $disabled = $this->makeForm(['status' => 'disable']);
        $draft = $this->makeForm(['publication_status' => 'draft']);
        $context = FormRuntimeContext::anonymous('ar');

        $this->assertFalse($this->policy->canRender($disabled, $context));
        $this->assertFalse($this->policy->canRender($draft, $context));
    }

    public function test_draft_save_requires_allow_draft_and_visibility(): void
    {
        $form = $this->makeForm([
            'visibility_settings' => ['mode' => 'public'],
            'submission_settings' => ['allow_draft' => true, 'limit' => 'unlimited'],
        ]);
        $runtime = $this->makeRuntime($form, draft: true);
        $context = FormRuntimeContext::anonymous('ar');

        $this->assertTrue($this->policy->canSaveDraft($form, $context, $runtime));
        $noDraftForm = $this->makeForm([
            'visibility_settings' => ['mode' => 'public'],
            'submission_settings' => ['allow_draft' => false],
        ]);

        $this->assertFalse($this->policy->canSaveDraft(
            $noDraftForm,
            $context,
            $this->makeRuntime($noDraftForm, draft: false),
        ));
    }

    public function test_update_draft_enforces_ownership(): void
    {
        $form = $this->makeForm([
            'visibility_settings' => ['mode' => 'public'],
            'submission_settings' => ['allow_draft' => true],
        ]);
        $runtime = $this->makeRuntime($form, draft: true);
        $owner = $this->contextFor($this->makeUser('student', 40));
        $other = $this->contextFor($this->makeUser('student', 41));
        $submission = $this->makeSubmission($form, 40);

        $this->assertTrue($this->policy->canUpdateDraft($form, $owner, $submission, $runtime));
        $this->assertFalse($this->policy->canUpdateDraft($form, $other, $submission, $runtime));
    }

    public function test_view_submission_allows_owner_and_staff(): void
    {
        $form = $this->makeForm(['visibility_settings' => ['mode' => 'public']]);
        $submission = $this->makeSubmission($form, 50);
        $owner = $this->contextFor($this->makeUser('student', 50));
        $other = $this->contextFor($this->makeUser('student', 51));
        $staff = $this->contextFor($this->makeUser('admin', 52, 'hr_officer'));

        $this->assertTrue($this->policy->canViewSubmission($form, $owner, $submission));
        $this->assertFalse($this->policy->canViewSubmission($form, $other, $submission));
        $this->assertTrue($this->policy->canViewSubmission($form, $staff, $submission));
    }

    public function test_staff_review_permission_required_for_status_transition(): void
    {
        $form = $this->makeForm();
        $submission = $this->makeSubmission($form, 60, FormSubmissionStatus::UNDER_REVIEW);
        $reviewer = $this->contextFor($this->makeUser('admin', 61, 'hr_officer'));
        $viewerOnly = $this->makeUser('admin', 62, 'finance_officer');

        $this->assertTrue($this->policy->canTransitionStatus(
            $form,
            $reviewer,
            $submission,
            FormSubmissionStatus::APPROVED,
        ));

        $this->assertFalse($this->policy->canTransitionStatus(
            $form,
            $this->contextFor($viewerOnly),
            $submission,
            FormSubmissionStatus::APPROVED,
        ));
    }

    public function test_snapshot_mismatch_is_denied_on_submit(): void
    {
        $form = $this->makeForm(['visibility_settings' => ['mode' => 'public']]);
        $runtime = $this->makeRuntime($form);
        $context = FormRuntimeContext::anonymous('ar');

        $this->assertFalse($this->policy->canSubmit($form, $context, $runtime, 'stale-hash'));
    }

    public function test_once_per_user_requires_authentication_on_submit(): void
    {
        $form = $this->makeForm([
            'visibility_settings' => ['mode' => 'public'],
            'submission_settings' => ['limit' => 'once_per_user'],
        ]);
        $runtime = $this->makeRuntime($form);

        $this->assertFalse($this->policy->canSubmit(
            $form,
            FormRuntimeContext::anonymous('ar'),
            $runtime,
            $runtime->snapshotHash(),
        ));

        $this->assertTrue($this->policy->canSubmit(
            $form,
            $this->contextFor($this->makeUser('student', 70)),
            $runtime,
            $runtime->snapshotHash(),
        ));
    }

    public function test_custom_roles_visibility_matches_admin_role(): void
    {
        $form = $this->makeForm([
            'visibility_settings' => [
                'mode' => 'custom_roles',
                'audiences' => ['hr_officer'],
            ],
        ]);

        $allowed = $this->contextFor($this->makeUser('admin', 80, 'hr_officer'));
        $denied = $this->contextFor($this->makeUser('admin', 81, 'finance_officer'));

        $this->assertTrue($this->policy->canRender($form, $allowed));
        $this->assertFalse($this->policy->canRender($form, $denied));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeForm(array $overrides = []): Form
    {
        return new Form(array_merge([
            'id' => 1,
            'name' => 'Test Form',
            'status' => 'enable',
            'publication_status' => 'published',
            'visibility_settings' => ['mode' => 'public', 'audiences' => []],
            'submission_settings' => ['limit' => 'unlimited', 'allow_draft' => false],
        ], $overrides));
    }

    protected function makeRuntime(Form $form, bool $draft = false): FormRuntimePayload
    {
        return new FormRuntimePayload(
            form: [
                'id' => $form->id,
                'version' => 2,
                'snapshot_hash' => 'snapshot-abc123',
            ],
            settings: [
                'submission' => [
                    'allow_draft' => $draft || (bool) ($form->submission_settings['allow_draft'] ?? false),
                ],
                'workflow' => ['enabled' => false, 'stages' => []],
            ],
            sections: [],
            logicRules: [],
            capabilities: [
                'anonymous' => ($form->visibility_settings['mode'] ?? 'public') === 'public',
                'draft' => $draft || (bool) ($form->submission_settings['allow_draft'] ?? false),
                'attachments' => false,
                'file_fields_accepted' => false,
            ],
        );
    }

    protected function makeSubmission(Form $form, int $userId, string $status = FormSubmissionStatus::DRAFT): FormSubmission
    {
        return new FormSubmission([
            'id' => 99,
            'form_id' => $form->id,
            'user_id' => $userId,
            'status' => $status,
            'data' => [],
            'timeline' => [],
        ]);
    }

    protected function makeUser(string $type, int $id, ?string $role = null): User
    {
        $user = new User([
            'user_type' => $type,
            'role' => $role,
            'name' => 'Test User',
        ]);
        $user->id = $id;

        return $user;
    }

    protected function contextFor(User $user): FormRuntimeContext
    {
        return FormRuntimeContext::forUser($user);
    }
}
