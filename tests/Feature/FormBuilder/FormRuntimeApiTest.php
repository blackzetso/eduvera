<?php

namespace Tests\Feature\FormBuilder;

use App\Models\Form;
use App\Models\FormInput;
use App\Models\FormSection;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\FormBuilder\FormSubmissionStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FormRuntimeApiTest extends TestCase
{
    protected int $userSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTables();
        $this->truncateTables();
        Auth::forgetGuards();
        RateLimiter::clear('form-runtime-get');
        RateLimiter::clear('form-submission-post');
        RateLimiter::clear('form-submission-read');
        RateLimiter::clear('form-submission-list');
        RateLimiter::clear('form-submission-review');
    }

    // ─── GET /runtime ───────────────────────────────────────────────────────

    public function test_r01_public_runtime_returns_full_payload(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $this->seedTextField($form);

        $response = $this->getJson("/api/forms/{$form->id}/runtime");

        $response->assertOk()
            ->assertJsonStructure(['form', 'settings', 'sections', 'logic_rules', 'capabilities']);
    }

    public function test_r02_runtime_locale_en(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $section = $this->seedTextField($form, labelEn: 'Name');

        $response = $this->getJson("/api/forms/{$form->id}/runtime?locale=en");

        $response->assertOk();
        $this->assertSame('Name', $response->json('sections.0.fields.0.label'));
    }

    public function test_r03_student_can_render_student_form(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'students']]);
        $this->seedTextField($form);

        $this->actingAs($this->makeStudent(), 'sanctum')
            ->getJson("/api/forms/{$form->id}/runtime")
            ->assertOk();
    }

    public function test_r04_parent_can_render_parent_form(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'parents']]);
        $this->seedTextField($form);

        $this->actingAs($this->makeParent(), 'sanctum')
            ->getJson("/api/forms/{$form->id}/runtime")
            ->assertOk();
    }

    public function test_r05_teacher_can_render_teacher_form(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'teachers']]);
        $this->seedTextField($form);

        $this->actingAs($this->makeTeacher(), 'sanctum')
            ->getJson("/api/forms/{$form->id}/runtime")
            ->assertOk();
    }

    public function test_r06_admin_can_render_staff_form(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'staff']]);
        $this->seedTextField($form);

        $this->actingAs($this->makeHrOfficer(), 'sanctum')
            ->getJson("/api/forms/{$form->id}/runtime")
            ->assertOk();
    }

    public function test_r07_staff_viewer_bypasses_staff_visibility(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'staff']]);
        $this->seedTextField($form);

        $this->actingAs($this->makeHrOfficer(), 'sanctum')
            ->getJson("/api/forms/{$form->id}/runtime")
            ->assertOk();
    }

    public function test_r08_anonymous_denied_student_form(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'students']]);
        $this->seedTextField($form);

        $this->getJson("/api/forms/{$form->id}/runtime")
            ->assertForbidden()
            ->assertJsonPath('reason', 'authentication_required');
    }

    public function test_r09_teacher_denied_student_form(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'students']]);
        $this->seedTextField($form);

        $this->actingAs($this->makeTeacher(), 'sanctum')
            ->getJson("/api/forms/{$form->id}/runtime")
            ->assertForbidden()
            ->assertJsonPath('reason', 'visibility_denied');
    }

    public function test_r10_parent_denied_student_form(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'students']]);
        $this->seedTextField($form);

        $this->actingAs($this->makeParent(), 'sanctum')
            ->getJson("/api/forms/{$form->id}/runtime")
            ->assertForbidden()
            ->assertJsonPath('reason', 'visibility_denied');
    }

    public function test_r11_custom_roles_match(): void
    {
        $form = $this->createForm([
            'visibility_settings' => ['mode' => 'custom_roles', 'audiences' => ['hr_officer']],
        ]);
        $this->seedTextField($form);

        $this->actingAs($this->makeHrOfficer(), 'sanctum')
            ->getJson("/api/forms/{$form->id}/runtime")
            ->assertOk();
    }

    public function test_r12_custom_roles_deny(): void
    {
        $form = $this->createForm([
            'visibility_settings' => ['mode' => 'custom_roles', 'audiences' => ['hr_officer']],
        ]);
        $this->seedTextField($form);

        $this->actingAs($this->makeFinanceOfficer(), 'sanctum')
            ->getJson("/api/forms/{$form->id}/runtime")
            ->assertForbidden()
            ->assertJsonPath('reason', 'visibility_custom_roles');
    }

    public function test_r13_draft_form_denied(): void
    {
        $form = $this->createForm([
            'visibility_settings' => ['mode' => 'public'],
            'publication_status' => 'draft',
        ]);
        $this->seedTextField($form);

        $this->getJson("/api/forms/{$form->id}/runtime")
            ->assertForbidden()
            ->assertJsonPath('reason', 'form_not_published');
    }

    public function test_r14_archived_form_denied(): void
    {
        $form = $this->createForm([
            'visibility_settings' => ['mode' => 'public'],
            'publication_status' => 'archived',
        ]);
        $this->seedTextField($form);

        $this->getJson("/api/forms/{$form->id}/runtime")
            ->assertForbidden()
            ->assertJsonPath('reason', 'form_not_published');
    }

    public function test_r15_disabled_form_denied(): void
    {
        $form = $this->createForm([
            'visibility_settings' => ['mode' => 'public'],
            'status' => 'disable',
        ]);
        $this->seedTextField($form);

        $this->getJson("/api/forms/{$form->id}/runtime")
            ->assertForbidden()
            ->assertJsonPath('reason', 'form_disabled');
    }

    public function test_r16_invalid_form_returns_not_found(): void
    {
        $this->getJson('/api/forms/99999/runtime')->assertNotFound();
    }

    public function test_r17_runtime_has_stable_keys(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $section = FormSection::create([
            'form_id' => $form->id,
            'title_ar' => 'قسم',
            'sort_order' => 1,
        ]);
        $input = FormInput::create([
            'form_id' => $form->id,
            'section_id' => $section->id,
            'sort_order' => 1,
            'name' => 'حقل',
            'type' => 'text',
            'schema' => ['label_ar' => 'حقل'],
        ]);

        $response = $this->getJson("/api/forms/{$form->id}/runtime")->assertOk();

        $this->assertSame('sec_'.$section->id, $response->json('sections.0.id'));
        $this->assertSame('fld_'.$input->id, $response->json('sections.0.fields.0.key'));
    }

    public function test_r19_snapshot_hash_present(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $this->seedTextField($form);

        $response = $this->getJson("/api/forms/{$form->id}/runtime")->assertOk();

        $this->assertNotEmpty($response->json('form.snapshot_hash'));
    }

    public function test_r20_runtime_rate_limit(): void
    {
        config(['form-builder.rate_limits.runtime_get' => 2]);
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $this->seedTextField($form);

        $this->getJson("/api/forms/{$form->id}/runtime")->assertOk();
        $this->getJson("/api/forms/{$form->id}/runtime")->assertOk();
        $this->getJson("/api/forms/{$form->id}/runtime")->assertStatus(429);
    }

    // ─── POST /submissions — draft ──────────────────────────────────────────

    public function test_d01_create_draft_anonymous(): void
    {
        $form = $this->createForm([
            'visibility_settings' => ['mode' => 'public'],
            'submission_settings' => ['allow_draft' => true, 'limit' => 'unlimited'],
        ]);
        $this->seedTextField($form);

        $this->postJson("/api/forms/{$form->id}/submissions", [
            'target_status' => 'draft',
            'data' => [],
        ])->assertCreated()
            ->assertJsonPath('submission.status', FormSubmissionStatus::DRAFT);
    }

    public function test_d02_create_draft_authenticated_student(): void
    {
        $form = $this->createForm([
            'visibility_settings' => ['mode' => 'students'],
            'submission_settings' => ['allow_draft' => true, 'limit' => 'unlimited'],
        ]);
        $input = $this->seedTextField($form);

        $this->actingAs($this->makeStudent(), 'sanctum')
            ->postJson("/api/forms/{$form->id}/submissions", [
                'target_status' => 'draft',
                'data' => ['fld_'.$input->id => 'partial'],
            ])
            ->assertCreated();
    }

    public function test_d03_update_own_draft(): void
    {
        $form = $this->createForm([
            'visibility_settings' => ['mode' => 'students'],
            'submission_settings' => ['allow_draft' => true, 'limit' => 'unlimited'],
        ]);
        $input = $this->seedTextField($form);
        $student = $this->makeStudent();

        $draft = $this->actingAs($student, 'sanctum')
            ->postJson("/api/forms/{$form->id}/submissions", [
                'target_status' => 'draft',
                'data' => ['fld_'.$input->id => 'v1'],
            ])
            ->assertCreated()
            ->json('submission.id');

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/forms/{$form->id}/submissions", [
                'target_status' => 'draft',
                'submission_id' => $draft,
                'data' => ['fld_'.$input->id => 'v2'],
            ])
            ->assertCreated()
            ->assertJsonPath('submission.id', $draft);
    }

    public function test_d04_draft_disabled(): void
    {
        $form = $this->createForm([
            'visibility_settings' => ['mode' => 'public'],
            'submission_settings' => ['allow_draft' => false, 'limit' => 'unlimited'],
        ]);
        $this->seedTextField($form);

        $this->postJson("/api/forms/{$form->id}/submissions", [
            'target_status' => 'draft',
            'data' => [],
        ])->assertForbidden()
            ->assertJsonPath('reason', 'draft_disabled');
    }

    public function test_d05_draft_visibility_denied(): void
    {
        $form = $this->createForm([
            'visibility_settings' => ['mode' => 'students'],
            'submission_settings' => ['allow_draft' => true, 'limit' => 'unlimited'],
        ]);
        $this->seedTextField($form);

        $this->postJson("/api/forms/{$form->id}/submissions", [
            'target_status' => 'draft',
            'data' => [],
        ])->assertForbidden();
    }

    public function test_d08_update_other_users_draft_denied(): void
    {
        $form = $this->createForm([
            'visibility_settings' => ['mode' => 'public'],
            'submission_settings' => ['allow_draft' => true, 'limit' => 'unlimited'],
        ]);
        $input = $this->seedTextField($form);
        $studentA = $this->makeStudent();
        $studentB = $this->makeStudent();

        $draftId = $this->actingAs($studentA, 'sanctum')
            ->postJson("/api/forms/{$form->id}/submissions", [
                'target_status' => 'draft',
                'data' => ['fld_'.$input->id => 'mine'],
            ])
            ->json('submission.id');

        $this->actingAs($studentB, 'sanctum')
            ->postJson("/api/forms/{$form->id}/submissions", [
                'target_status' => 'draft',
                'submission_id' => $draftId,
                'data' => ['fld_'.$input->id => 'stolen'],
            ])
            ->assertForbidden()
            ->assertJsonPath('reason', 'ownership_denied');
    }

    public function test_d09_update_non_draft_denied(): void
    {
        $form = $this->createForm([
            'visibility_settings' => ['mode' => 'public'],
            'submission_settings' => ['allow_draft' => true, 'limit' => 'unlimited'],
        ]);
        $input = $this->seedTextField($form);
        $student = $this->makeStudent();
        $hash = $this->fetchSnapshotHash($form);

        $submissionId = $this->actingAs($student, 'sanctum')
            ->postJson("/api/forms/{$form->id}/submissions", [
                'data' => ['fld_'.$input->id => 'done'],
                'snapshot_hash' => $hash,
            ])
            ->json('submission.id');

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/forms/{$form->id}/submissions", [
                'target_status' => 'draft',
                'submission_id' => $submissionId,
                'data' => ['fld_'.$input->id => 'retry'],
            ])
            ->assertForbidden()
            ->assertJsonPath('reason', 'not_draft');
    }

    public function test_d10_draft_snapshot_not_required(): void
    {
        $form = $this->createForm([
            'visibility_settings' => ['mode' => 'public'],
            'submission_settings' => ['allow_draft' => true, 'limit' => 'unlimited'],
        ]);
        $this->seedTextField($form);

        $this->postJson("/api/forms/{$form->id}/submissions", [
            'target_status' => 'draft',
            'data' => [],
        ])->assertCreated();
    }

    public function test_d11_draft_skips_validation(): void
    {
        $form = $this->createForm([
            'visibility_settings' => ['mode' => 'public'],
            'submission_settings' => ['allow_draft' => true, 'limit' => 'unlimited'],
        ]);
        $this->seedTextField($form, required: true);

        $this->postJson("/api/forms/{$form->id}/submissions", [
            'target_status' => 'draft',
            'data' => [],
        ])->assertCreated();
    }

    public function test_d12_unknown_field_keys_stripped(): void
    {
        $form = $this->createForm([
            'visibility_settings' => ['mode' => 'public'],
            'submission_settings' => ['allow_draft' => true, 'limit' => 'unlimited'],
        ]);
        $input = $this->seedTextField($form);

        $this->postJson("/api/forms/{$form->id}/submissions", [
            'target_status' => 'draft',
            'data' => [
                'fld_'.$input->id => 'ok',
                'fld_unknown' => 'hack',
            ],
        ])->assertCreated();

        $submission = FormSubmission::first();
        $this->assertArrayHasKey('fld_'.$input->id, $submission->data);
        $this->assertArrayNotHasKey('fld_unknown', $submission->data);
    }

    public function test_d13_draft_timeline_created_event(): void
    {
        $form = $this->createForm([
            'visibility_settings' => ['mode' => 'public'],
            'submission_settings' => ['allow_draft' => true, 'limit' => 'unlimited'],
        ]);
        $this->seedTextField($form);

        $this->postJson("/api/forms/{$form->id}/submissions", [
            'target_status' => 'draft',
            'data' => [],
        ])->assertCreated();

        $this->assertSame('created', FormSubmission::first()->timeline[0]['event']);
    }

    public function test_d14_draft_rate_limit(): void
    {
        config(['form-builder.rate_limits.submission_post' => 2]);
        $form = $this->createForm([
            'visibility_settings' => ['mode' => 'public'],
            'submission_settings' => ['allow_draft' => true, 'limit' => 'unlimited'],
        ]);
        $this->seedTextField($form);

        $this->postJson("/api/forms/{$form->id}/submissions", ['target_status' => 'draft', 'data' => []])->assertCreated();
        $this->postJson("/api/forms/{$form->id}/submissions", ['target_status' => 'draft', 'data' => []])->assertCreated();
        $this->postJson("/api/forms/{$form->id}/submissions", ['target_status' => 'draft', 'data' => []])->assertStatus(429);
    }

    // ─── POST /submissions — submit ─────────────────────────────────────────

    public function test_s01_public_submit(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $input = $this->seedTextField($form);
        $hash = $this->fetchSnapshotHash($form);

        $this->postJson("/api/forms/{$form->id}/submissions", [
            'data' => ['fld_'.$input->id => 'Sara'],
            'snapshot_hash' => $hash,
        ])->assertCreated()
            ->assertJsonPath('submission.status', FormSubmissionStatus::SUBMITTED);
    }

    public function test_s02_student_submit(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'students']]);
        $input = $this->seedTextField($form);
        $hash = $this->fetchSnapshotHash($form, $this->makeStudent());

        $this->actingAs($this->makeStudent(), 'sanctum')
            ->postJson("/api/forms/{$form->id}/submissions", [
                'data' => ['fld_'.$input->id => 'Sara'],
                'snapshot_hash' => $hash,
            ])
            ->assertCreated();
    }

    public function test_s03_finalize_draft(): void
    {
        $form = $this->createForm([
            'visibility_settings' => ['mode' => 'students'],
            'submission_settings' => ['allow_draft' => true, 'limit' => 'unlimited'],
        ]);
        $input = $this->seedTextField($form);
        $student = $this->makeStudent();

        $draftId = $this->actingAs($student, 'sanctum')
            ->postJson("/api/forms/{$form->id}/submissions", [
                'target_status' => 'draft',
                'data' => ['fld_'.$input->id => 'draft'],
            ])
            ->json('submission.id');

        $hash = $this->fetchSnapshotHash($form, $student);

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/forms/{$form->id}/submissions", [
                'submission_id' => $draftId,
                'data' => ['fld_'.$input->id => 'final'],
                'snapshot_hash' => $hash,
            ])
            ->assertCreated()
            ->assertJsonPath('submission.id', $draftId)
            ->assertJsonPath('submission.status', FormSubmissionStatus::SUBMITTED);
    }

    public function test_s04_workflow_submit(): void
    {
        $form = $this->createForm([
            'visibility_settings' => ['mode' => 'public'],
            'workflow_definition' => ['enabled' => true, 'stages' => [['id' => 'stage_1']]],
        ]);
        $input = $this->seedTextField($form);
        $hash = $this->fetchSnapshotHash($form);

        $this->postJson("/api/forms/{$form->id}/submissions", [
            'data' => ['fld_'.$input->id => 'Sara'],
            'snapshot_hash' => $hash,
        ])->assertCreated()
            ->assertJsonPath('submission.status', FormSubmissionStatus::UNDER_REVIEW)
            ->assertJsonPath('submission.workflow_stage', 'stage_1');
    }

    public function test_s05_bilingual_success_messages(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $input = $this->seedTextField($form);
        $hash = $this->fetchSnapshotHash($form);

        $this->postJson("/api/forms/{$form->id}/submissions", [
            'locale' => 'en',
            'data' => ['fld_'.$input->id => 'Sara'],
            'snapshot_hash' => $hash,
        ])->assertCreated()
            ->assertJsonPath('message_en', 'Form submitted successfully');
    }

    public function test_s06_snapshot_mismatch(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $input = $this->seedTextField($form);

        $this->postJson("/api/forms/{$form->id}/submissions", [
            'data' => ['fld_'.$input->id => 'Sara'],
            'snapshot_hash' => 'stale-hash',
        ])->assertStatus(409)
            ->assertJsonPath('reason', 'snapshot_mismatch');
    }

    public function test_s07_snapshot_missing(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $input = $this->seedTextField($form);

        $this->postJson("/api/forms/{$form->id}/submissions", [
            'data' => ['fld_'.$input->id => 'Sara'],
        ])->assertForbidden()
            ->assertJsonPath('reason', 'snapshot_required');
    }

    public function test_s08_validation_required_field(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $this->seedTextField($form, required: true);
        $hash = $this->fetchSnapshotHash($form);

        $this->postJson("/api/forms/{$form->id}/submissions", [
            'data' => [],
            'snapshot_hash' => $hash,
        ])->assertUnprocessable()
            ->assertJsonPath('reason', 'validation_failed');
    }

    public function test_s09_validation_email_format(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $input = $this->seedEmailField($form);
        $hash = $this->fetchSnapshotHash($form);

        $this->postJson("/api/forms/{$form->id}/submissions", [
            'data' => ['fld_'.$input->id => 'not-an-email'],
            'snapshot_hash' => $hash,
        ])->assertUnprocessable()
            ->assertJsonPath('reason', 'validation_failed');
    }

    public function test_s11_anonymous_denied_students_form(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'students']]);
        $input = $this->seedTextField($form);

        $this->postJson("/api/forms/{$form->id}/submissions", [
            'data' => ['fld_'.$input->id => 'Sara'],
            'snapshot_hash' => 'any-hash',
        ])->assertForbidden();
    }

    public function test_s12_once_per_user_requires_auth(): void
    {
        $form = $this->createForm([
            'visibility_settings' => ['mode' => 'public'],
            'submission_settings' => ['limit' => 'once_per_user'],
        ]);
        $input = $this->seedTextField($form);
        $hash = $this->fetchSnapshotHash($form);

        $this->postJson("/api/forms/{$form->id}/submissions", [
            'data' => ['fld_'.$input->id => 'Sara'],
            'snapshot_hash' => $hash,
        ])->assertForbidden()
            ->assertJsonPath('reason', 'authentication_required');
    }

    public function test_s13_once_per_user_duplicate(): void
    {
        $form = $this->createForm([
            'visibility_settings' => ['mode' => 'public'],
            'submission_settings' => ['limit' => 'once_per_user'],
        ]);
        $input = $this->seedTextField($form);
        $student = $this->makeStudent();
        $hash = $this->fetchSnapshotHash($form, $student);

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/forms/{$form->id}/submissions", [
                'data' => ['fld_'.$input->id => 'first'],
                'snapshot_hash' => $hash,
            ])
            ->assertCreated();

        $hash = $this->fetchSnapshotHash($form, $student);

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/forms/{$form->id}/submissions", [
                'data' => ['fld_'.$input->id => 'second'],
                'snapshot_hash' => $hash,
            ])
            ->assertForbidden();
    }

    public function test_s14_submission_window_closed(): void
    {
        $form = $this->createForm([
            'visibility_settings' => ['mode' => 'public'],
            'submission_settings' => [
                'limit' => 'date_range',
                'date_to' => now()->subDay()->toDateString(),
            ],
        ]);
        $input = $this->seedTextField($form);
        $hash = $this->fetchSnapshotHash($form);

        $this->postJson("/api/forms/{$form->id}/submissions", [
            'data' => ['fld_'.$input->id => 'Sara'],
            'snapshot_hash' => $hash,
        ])->assertForbidden()
            ->assertJsonPath('reason', 'submission_window_closed');
    }

    public function test_s15_submission_window_not_open(): void
    {
        $form = $this->createForm([
            'visibility_settings' => ['mode' => 'public'],
            'submission_settings' => [
                'limit' => 'date_range',
                'date_from' => now()->addDay()->toDateString(),
            ],
        ]);
        $input = $this->seedTextField($form);
        $hash = $this->fetchSnapshotHash($form);

        $this->postJson("/api/forms/{$form->id}/submissions", [
            'data' => ['fld_'.$input->id => 'Sara'],
            'snapshot_hash' => $hash,
        ])->assertForbidden()
            ->assertJsonPath('reason', 'submission_window_closed');
    }

    public function test_s18_timeline_submitted_event(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $input = $this->seedTextField($form);
        $hash = $this->fetchSnapshotHash($form);

        $this->postJson("/api/forms/{$form->id}/submissions", [
            'data' => ['fld_'.$input->id => 'Sara'],
            'snapshot_hash' => $hash,
        ])->assertCreated();

        $events = collect(FormSubmission::first()->timeline)->pluck('event');
        $this->assertTrue($events->contains('submitted'));
    }

    public function test_s19_snapshot_stored_in_meta(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $input = $this->seedTextField($form);
        $hash = $this->fetchSnapshotHash($form);

        $this->postJson("/api/forms/{$form->id}/submissions", [
            'data' => ['fld_'.$input->id => 'Sara'],
            'snapshot_hash' => $hash,
        ])->assertCreated();

        $this->assertSame($hash, FormSubmission::first()->data['_meta']['snapshot']['snapshot_hash']);
    }

    public function test_s20_submit_rate_limit(): void
    {
        config(['form-builder.rate_limits.submission_post' => 2]);
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $input = $this->seedTextField($form);

        for ($i = 0; $i < 2; $i++) {
            $hash = $this->fetchSnapshotHash($form);
            $this->postJson("/api/forms/{$form->id}/submissions", [
                'data' => ['fld_'.$input->id => "Sara {$i}"],
                'snapshot_hash' => $hash,
            ])->assertCreated();
        }

        $hash = $this->fetchSnapshotHash($form);
        $this->postJson("/api/forms/{$form->id}/submissions", [
            'data' => ['fld_'.$input->id => 'Sara 3'],
            'snapshot_hash' => $hash,
        ])->assertStatus(429);
    }

    // ─── GET /submissions/{submission} ──────────────────────────────────────

    public function test_v01_owner_views_own_submission(): void
    {
        [$form, $submission, $student] = $this->seedOwnedSubmission();

        $this->actingAs($student, 'sanctum')
            ->getJson("/api/forms/{$form->id}/submissions/{$submission->id}")
            ->assertOk()
            ->assertJsonStructure(['submission' => ['data', 'timeline', 'submitter']]);
    }

    public function test_v02_staff_reviewer_views_submission(): void
    {
        [$form, $submission] = $this->seedOwnedSubmission();

        $this->actingAs($this->makeHrOfficer(), 'sanctum')
            ->getJson("/api/forms/{$form->id}/submissions/{$submission->id}")
            ->assertOk();
    }

    public function test_v04_ownership_denied(): void
    {
        [$form, $submission] = $this->seedOwnedSubmission();

        $this->actingAs($this->makeStudent(), 'sanctum')
            ->getJson("/api/forms/{$form->id}/submissions/{$submission->id}")
            ->assertForbidden()
            ->assertJsonPath('reason', 'ownership_denied');
    }

    public function test_v05_anonymous_denied(): void
    {
        [$form, $submission] = $this->seedOwnedSubmission();

        $this->getJson("/api/forms/{$form->id}/submissions/{$submission->id}")
            ->assertUnauthorized();
    }

    public function test_v06_finance_officer_denied(): void
    {
        [$form, $submission] = $this->seedOwnedSubmission();

        $this->actingAs($this->makeFinanceOfficer(), 'sanctum')
            ->getJson("/api/forms/{$form->id}/submissions/{$submission->id}")
            ->assertForbidden();
    }

    public function test_v07_cross_form_mismatch(): void
    {
        [$form, $submission, $student] = $this->seedOwnedSubmission();
        $otherForm = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $this->seedTextField($otherForm);

        $this->actingAs($student, 'sanctum')
            ->getJson("/api/forms/{$otherForm->id}/submissions/{$submission->id}")
            ->assertNotFound();
    }

    public function test_v08_submission_not_found(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $this->seedTextField($form);

        $this->actingAs($this->makeStudent(), 'sanctum')
            ->getJson("/api/forms/{$form->id}/submissions/99999")
            ->assertNotFound();
    }

    public function test_v10_anonymous_submitter_label_for_staff(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $input = $this->seedTextField($form);
        $hash = $this->fetchSnapshotHash($form);

        $submissionId = $this->postJson("/api/forms/{$form->id}/submissions", [
            'data' => ['fld_'.$input->id => 'Guest'],
            'snapshot_hash' => $hash,
        ])->json('submission.id');

        $this->actingAs($this->makeHrOfficer(), 'sanctum')
            ->getJson("/api/forms/{$form->id}/submissions/{$submissionId}")
            ->assertOk()
            ->assertJsonPath('submission.submitter.type', 'anonymous');
    }

    // ─── GET /submissions (list) ────────────────────────────────────────────

    public function test_l01_staff_list(): void
    {
        [$form] = $this->seedOwnedSubmission();

        $this->actingAs($this->makeHrOfficer(), 'sanctum')
            ->getJson("/api/forms/{$form->id}/submissions")
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'form']);
    }

    public function test_l02_filter_by_status(): void
    {
        [$form] = $this->seedOwnedSubmission();

        $this->actingAs($this->makeHrOfficer(), 'sanctum')
            ->getJson("/api/forms/{$form->id}/submissions?status=submitted")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_l04_pagination(): void
    {
        [$form] = $this->seedOwnedSubmission();

        $this->actingAs($this->makeHrOfficer(), 'sanctum')
            ->getJson("/api/forms/{$form->id}/submissions?page=1&per_page=10")
            ->assertOk()
            ->assertJsonPath('meta.per_page', 10);
    }

    public function test_l06_summary_present(): void
    {
        [$form] = $this->seedOwnedSubmission();

        $response = $this->actingAs($this->makeHrOfficer(), 'sanctum')
            ->getJson("/api/forms/{$form->id}/submissions")
            ->assertOk();

        $this->assertArrayHasKey('summary', $response->json('data.0'));
        $this->assertArrayNotHasKey('_meta', $response->json('data.0.summary'));
    }

    public function test_l07_anonymous_list_denied(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $this->seedTextField($form);

        $this->getJson("/api/forms/{$form->id}/submissions")->assertUnauthorized();
    }

    public function test_l08_student_list_denied(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $this->seedTextField($form);

        $this->actingAs($this->makeStudent(), 'sanctum')
            ->getJson("/api/forms/{$form->id}/submissions")
            ->assertForbidden()
            ->assertJsonPath('reason', 'list_denied');
    }

    public function test_l09_finance_officer_list_denied(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $this->seedTextField($form);

        $this->actingAs($this->makeFinanceOfficer(), 'sanctum')
            ->getJson("/api/forms/{$form->id}/submissions")
            ->assertForbidden();
    }

    public function test_l10_empty_list(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $this->seedTextField($form);

        $this->actingAs($this->makeHrOfficer(), 'sanctum')
            ->getJson("/api/forms/{$form->id}/submissions")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // ─── PATCH /status ──────────────────────────────────────────────────────

    public function test_p01_approve_submission(): void
    {
        [$form, $submission] = $this->seedUnderReviewSubmission();

        $this->actingAs($this->makeHrOfficer(), 'sanctum')
            ->patchJson("/api/forms/{$form->id}/submissions/{$submission->id}/status", [
                'status' => 'approved',
            ])
            ->assertOk()
            ->assertJsonPath('submission.status', FormSubmissionStatus::APPROVED);
    }

    public function test_p02_reject_submission(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $input = $this->seedTextField($form);
        $hash = $this->fetchSnapshotHash($form);

        $submissionId = $this->postJson("/api/forms/{$form->id}/submissions", [
            'data' => ['fld_'.$input->id => 'Sara'],
            'snapshot_hash' => $hash,
        ])->json('submission.id');

        $this->actingAs($this->makeHrOfficer(), 'sanctum')
            ->patchJson("/api/forms/{$form->id}/submissions/{$submissionId}/status", [
                'status' => 'rejected',
                'comment' => 'Incomplete',
            ])
            ->assertOk()
            ->assertJsonPath('submission.status', FormSubmissionStatus::REJECTED);
    }

    public function test_p03_return_to_draft(): void
    {
        [$form, $submission] = $this->seedUnderReviewSubmission();

        $this->actingAs($this->makeHrOfficer(), 'sanctum')
            ->patchJson("/api/forms/{$form->id}/submissions/{$submission->id}/status", [
                'status' => 'draft',
            ])
            ->assertOk()
            ->assertJsonPath('submission.status', FormSubmissionStatus::DRAFT);
    }

    public function test_p04_timeline_status_changed(): void
    {
        [$form, $submission] = $this->seedUnderReviewSubmission();

        $this->actingAs($this->makeHrOfficer(), 'sanctum')
            ->patchJson("/api/forms/{$form->id}/submissions/{$submission->id}/status", [
                'status' => 'approved',
                'comment' => 'OK',
            ])
            ->assertOk();

        $events = collect(FormSubmission::find($submission->id)->timeline)->pluck('event');
        $this->assertTrue($events->contains('status_changed'));
    }

    public function test_p05_finance_officer_transition_denied(): void
    {
        [$form, $submission] = $this->seedUnderReviewSubmission();

        $this->actingAs($this->makeFinanceOfficer(), 'sanctum')
            ->patchJson("/api/forms/{$form->id}/submissions/{$submission->id}/status", [
                'status' => 'approved',
            ])
            ->assertForbidden()
            ->assertJsonPath('reason', 'transition_denied');
    }

    public function test_p06_owner_cannot_transition(): void
    {
        [$form, $submission, $student] = $this->seedOwnedSubmission();

        $this->actingAs($student, 'sanctum')
            ->patchJson("/api/forms/{$form->id}/submissions/{$submission->id}/status", [
                'status' => 'approved',
            ])
            ->assertForbidden();
    }

    public function test_p07_anonymous_patch_denied(): void
    {
        [$form, $submission] = $this->seedUnderReviewSubmission();

        $this->patchJson("/api/forms/{$form->id}/submissions/{$submission->id}/status", [
            'status' => 'approved',
        ])->assertUnauthorized();
    }

    public function test_p08_invalid_transition(): void
    {
        [$form, $submission] = $this->seedOwnedSubmission();

        $this->actingAs($this->makeHrOfficer(), 'sanctum')
            ->patchJson("/api/forms/{$form->id}/submissions/{$submission->id}/status", [
                'status' => 'draft',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'invalid_transition');
    }

    public function test_p10_missing_status_field(): void
    {
        [$form, $submission] = $this->seedUnderReviewSubmission();

        $this->actingAs($this->makeHrOfficer(), 'sanctum')
            ->patchJson("/api/forms/{$form->id}/submissions/{$submission->id}/status", [])
            ->assertUnprocessable();
    }

    public function test_p13_comment_stored_in_timeline(): void
    {
        [$form, $submission] = $this->seedUnderReviewSubmission();

        $this->actingAs($this->makeHrOfficer(), 'sanctum')
            ->patchJson("/api/forms/{$form->id}/submissions/{$submission->id}/status", [
                'status' => 'rejected',
                'comment' => 'Missing documents',
            ])
            ->assertOk();

        $last = collect(FormSubmission::find($submission->id)->timeline)->last();
        $this->assertSame('Missing documents', $last['comment']);
    }

    // ─── Security suite ─────────────────────────────────────────────────────

    public function test_sec01_idor_view_other_submission(): void
    {
        [$form, $submission] = $this->seedOwnedSubmission();

        $this->actingAs($this->makeStudent(), 'sanctum')
            ->getJson("/api/forms/{$form->id}/submissions/{$submission->id}")
            ->assertForbidden();
    }

    public function test_sec02_idor_update_other_draft(): void
    {
        $this->test_d08_update_other_users_draft_denied();
    }

    public function test_sec03_cross_form_submission_access(): void
    {
        $this->test_v07_cross_form_mismatch();
    }

    public function test_sec04_student_cannot_approve(): void
    {
        $this->test_p06_owner_cannot_transition();
    }

    public function test_sec05_student_cannot_list(): void
    {
        $this->test_l08_student_list_denied();
    }

    public function test_sec06_finance_officer_cannot_review(): void
    {
        $this->test_p05_finance_officer_transition_denied();
    }

    public function test_sec07_anonymous_cannot_read_student_runtime(): void
    {
        $this->test_r08_anonymous_denied_student_form();
    }

    public function test_sec09_stale_snapshot_after_form_edit(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $input = $this->seedTextField($form);
        $staleHash = $this->fetchSnapshotHash($form);

        FormInput::find($input->id)->update([
            'schema' => [
                'label_ar' => 'الاسم المحدث',
                'validation' => ['required' => true],
            ],
        ]);

        $this->postJson("/api/forms/{$form->id}/submissions", [
            'data' => ['fld_'.$input->id => 'Sara'],
            'snapshot_hash' => $staleHash,
        ])->assertStatus(409);
    }

    public function test_sec10_unpublished_runtime_denied(): void
    {
        $this->test_r13_draft_form_denied();
    }

    public function test_sec13_unknown_field_stripped(): void
    {
        $this->test_d12_unknown_field_keys_stripped();
    }

    public function test_sec14_anonymous_cannot_view_anonymous_submission(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $input = $this->seedTextField($form);
        $hash = $this->fetchSnapshotHash($form);

        $submissionId = $this->postJson("/api/forms/{$form->id}/submissions", [
            'data' => ['fld_'.$input->id => 'Guest'],
            'snapshot_hash' => $hash,
        ])->json('submission.id');

        $this->getJson("/api/forms/{$form->id}/submissions/{$submissionId}")
            ->assertUnauthorized();
    }

    public function test_sec17_custom_roles_bypass_denied(): void
    {
        $this->test_r12_custom_roles_deny();
    }

    public function test_sec18_once_per_user_anonymous_denied(): void
    {
        $this->test_s12_once_per_user_requires_auth();
    }

    public function test_sec20_patch_without_auth(): void
    {
        $this->test_p07_anonymous_patch_denied();
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    protected function fetchSnapshotHash(Form $form, ?User $user = null): string
    {
        $request = $user
            ? $this->actingAs($user, 'sanctum')
            : $this;

        return $request->getJson("/api/forms/{$form->id}/runtime")
            ->json('form.snapshot_hash');
    }

    /**
     * @return array{0: Form, 1: FormSubmission, 2: User}
     */
    protected function seedOwnedSubmission(): array
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'students']]);
        $input = $this->seedTextField($form);
        $student = $this->makeStudent();
        $hash = $this->fetchSnapshotHash($form, $student);

        $submissionId = $this->actingAs($student, 'sanctum')
            ->postJson("/api/forms/{$form->id}/submissions", [
                'data' => ['fld_'.$input->id => 'Sara'],
                'snapshot_hash' => $hash,
            ])
            ->json('submission.id');

        Auth::forgetGuards();

        return [$form, FormSubmission::find($submissionId), $student];
    }

    /**
     * @return array{0: Form, 1: FormSubmission}
     */
    protected function seedUnderReviewSubmission(): array
    {
        $form = $this->createForm([
            'visibility_settings' => ['mode' => 'public'],
            'workflow_definition' => ['enabled' => true, 'stages' => [['id' => 'stage_1']]],
        ]);
        $input = $this->seedTextField($form);
        $hash = $this->fetchSnapshotHash($form);

        $submissionId = $this->postJson("/api/forms/{$form->id}/submissions", [
            'data' => ['fld_'.$input->id => 'Sara'],
            'snapshot_hash' => $hash,
        ])->json('submission.id');

        return [$form, FormSubmission::find($submissionId)];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createForm(array $overrides = []): Form
    {
        return Form::create(array_merge([
            'name' => 'نموذج',
            'status' => 'enable',
            'publication_status' => 'published',
            'version' => 2,
            'visibility_settings' => ['mode' => 'public', 'audiences' => []],
            'submission_settings' => ['limit' => 'unlimited', 'allow_draft' => false],
            'workflow_definition' => ['enabled' => false, 'stages' => []],
        ], $overrides));
    }

    protected function seedTextField(Form $form, bool $required = false, ?string $labelEn = null): FormInput
    {
        $section = FormSection::firstOrCreate(
            ['form_id' => $form->id, 'sort_order' => 1],
            ['title_ar' => 'قسم'],
        );

        return FormInput::create([
            'form_id' => $form->id,
            'section_id' => $section->id,
            'sort_order' => 1,
            'name' => 'الاسم',
            'type' => 'text',
            'required' => $required,
            'schema' => [
                'label_ar' => 'الاسم',
                'label_en' => $labelEn,
                'validation' => ['required' => $required],
            ],
        ]);
    }

    protected function seedEmailField(Form $form): FormInput
    {
        $section = FormSection::firstOrCreate(
            ['form_id' => $form->id, 'sort_order' => 1],
            ['title_ar' => 'قسم'],
        );

        return FormInput::create([
            'form_id' => $form->id,
            'section_id' => $section->id,
            'sort_order' => 2,
            'name' => 'البريد',
            'type' => 'email',
            'required' => true,
            'schema' => [
                'label_ar' => 'البريد',
                'validation' => ['required' => true, 'email' => true],
            ],
        ]);
    }

    protected function makeStudent(?string $email = null): User
    {
        $this->userSequence++;

        return User::create([
            'name' => 'Student',
            'email' => $email ?? "student{$this->userSequence}@example.com",
            'password' => Hash::make('password'),
            'user_type' => 'student',
        ]);
    }

    protected function makeParent(): User
    {
        return User::create([
            'name' => 'Parent',
            'email' => 'parent@example.com',
            'password' => Hash::make('password'),
            'user_type' => 'guardian',
        ]);
    }

    protected function makeTeacher(): User
    {
        return User::create([
            'name' => 'Teacher',
            'email' => 'teacher@example.com',
            'password' => Hash::make('password'),
            'user_type' => 'teacher',
        ]);
    }

    protected function makeHrOfficer(): User
    {
        return User::create([
            'name' => 'HR Officer',
            'email' => 'hr@example.com',
            'password' => Hash::make('password'),
            'user_type' => 'admin',
            'role' => 'hr_officer',
        ]);
    }

    protected function makeFinanceOfficer(): User
    {
        return User::create([
            'name' => 'Finance Officer',
            'email' => 'finance@example.com',
            'password' => Hash::make('password'),
            'user_type' => 'admin',
            'role' => 'finance_officer',
        ]);
    }

    protected function ensureTables(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('user_type')->default('student');
                $table->string('role')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('forms')) {
            Schema::create('forms', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('name_en')->nullable();
                $table->text('description_ar')->nullable();
                $table->text('description_en')->nullable();
                $table->string('status')->default('enable');
                $table->string('publication_status')->default('draft');
                $table->unsignedSmallInteger('version')->default(2);
                $table->string('template_key')->nullable();
                $table->json('visibility_settings')->nullable();
                $table->json('submission_settings')->nullable();
                $table->json('workflow_definition')->nullable();
                $table->json('logic_rules')->nullable();
                $table->json('builder_settings')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('form_sections')) {
            Schema::create('form_sections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('form_id');
                $table->string('title_ar');
                $table->string('title_en')->nullable();
                $table->text('description_ar')->nullable();
                $table->text('description_en')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_collapsed')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('form_inputs')) {
            Schema::create('form_inputs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('form_id');
                $table->unsignedBigInteger('section_id')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('name');
                $table->string('label_en')->nullable();
                $table->string('type');
                $table->boolean('required')->default(false);
                $table->json('options')->nullable();
                $table->json('schema')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('form_submissions')) {
            Schema::create('form_submissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('form_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('status', 30)->default('submitted');
                $table->string('workflow_stage')->nullable();
                $table->json('data');
                $table->json('timeline')->nullable();
                $table->string('locale', 5)->default('ar');
                $table->string('ip_address')->nullable();
                $table->timestamps();
            });
        }
    }

    protected function truncateTables(): void
    {
        FormSubmission::query()->delete();
        FormInput::query()->delete();
        FormSection::query()->delete();
        Form::query()->delete();
        User::query()->delete();
    }
}
