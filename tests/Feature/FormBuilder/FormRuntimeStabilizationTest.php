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
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FormRuntimeStabilizationTest extends TestCase
{
    protected int $userSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTables();
        $this->truncateTables();
        Auth::forgetGuards();
    }

    // ─── P0-1 Resume flow ───────────────────────────────────────────────────

    public function test_f01_resume_own_draft_returns_data(): void
    {
        [$form, $draftId, $student, $input] = $this->seedStudentDraft(['fld_value' => 'Draft Value']);

        $this->actingAs($student, 'sanctum')
            ->getJson("/api/forms/{$form->id}/submissions/{$draftId}")
            ->assertOk()
            ->assertJsonPath('submission.status', FormSubmissionStatus::DRAFT)
            ->assertJsonPath("submission.data.fld_{$input->id}", 'Draft Value');
    }

    public function test_f02_resume_authenticated_draft_for_update(): void
    {
        [$form, $draftId, $student, $input] = $this->seedStudentDraft(['fld_value' => 'v1']);

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/forms/{$form->id}/submissions", [
                'target_status' => 'draft',
                'submission_id' => $draftId,
                'data' => ['fld_'.$input->id => 'v2-resumed'],
            ])
            ->assertCreated()
            ->assertJsonPath('submission.id', $draftId);

        $submission = FormSubmission::find($draftId);

        $this->assertSame('v2-resumed', $submission->data['fld_'.$input->id]);
    }

    public function test_f03_unauthorized_resume_denied(): void
    {
        [$form, $draftId] = $this->seedStudentDraft(['fld_value' => 'private']);

        $this->actingAs($this->makeStudent(), 'sanctum')
            ->getJson("/api/forms/{$form->id}/submissions/{$draftId}")
            ->assertForbidden()
            ->assertJsonPath('reason', 'ownership_denied');
    }

    public function test_f04_anonymous_resume_denied(): void
    {
        [$form, $draftId] = $this->seedStudentDraft(['fld_value' => 'private']);

        $this->getJson("/api/forms/{$form->id}/submissions/{$draftId}")
            ->assertUnauthorized();
    }

    // ─── P0-2 Unsupported type alignment ────────────────────────────────────

    public function test_f05_runtime_exposes_supported_field_types(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $this->seedTextField($form);

        $response = $this->getJson("/api/forms/{$form->id}/runtime");

        $response->assertOk()
            ->assertJsonPath('capabilities.supported_field_types', config('form-builder.supported_runtime_field_types'));
    }

    public function test_f06_unsupported_field_not_persisted(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $text = $this->seedTextField($form);
        $grade = $this->seedTypedField($form, 'grade', 2);
        $hash = $this->fetchSnapshotHash($form);

        $this->postJson("/api/forms/{$form->id}/submissions", [
            'data' => [
                'fld_'.$text->id => 'Valid',
                'fld_'.$grade->id => 'Grade 5',
                'fld_unknown' => 'Injected',
            ],
            'snapshot_hash' => $hash,
        ])->assertCreated();

        $submission = FormSubmission::latest()->first();

        $this->assertSame('Valid', $submission->data['fld_'.$text->id]);
        $this->assertArrayNotHasKey('fld_'.$grade->id, $submission->data);
        $this->assertArrayNotHasKey('fld_unknown', $submission->data);
    }

    public function test_f07_unsupported_required_field_not_validated(): void
    {
        $form = $this->createForm(['visibility_settings' => ['mode' => 'public']]);
        $this->seedTypedField($form, 'academic_year', 1, required: true);
        $hash = $this->fetchSnapshotHash($form);

        $this->postJson("/api/forms/{$form->id}/submissions", [
            'data' => [],
            'snapshot_hash' => $hash,
        ])->assertCreated();
    }

    public function test_f08_supported_fields_still_validated(): void
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

    // ─── Helpers ────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Form, 1: int, 2: User, 3: FormInput}
     */
    protected function seedStudentDraft(array $data): array
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
                'data' => ['fld_'.$input->id => $data['fld_value']],
            ])
            ->assertCreated()
            ->json('submission.id');

        Auth::forgetGuards();

        return [$form, $draftId, $student, $input];
    }

    protected function fetchSnapshotHash(Form $form): string
    {
        return $this->getJson("/api/forms/{$form->id}/runtime")
            ->json('form.snapshot_hash');
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

    protected function seedTextField(Form $form, bool $required = false): FormInput
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
                'validation' => ['required' => $required],
            ],
        ]);
    }

    protected function seedTypedField(Form $form, string $type, int $order, bool $required = false): FormInput
    {
        $section = FormSection::firstOrCreate(
            ['form_id' => $form->id, 'sort_order' => 1],
            ['title_ar' => 'قسم'],
        );

        return FormInput::create([
            'form_id' => $form->id,
            'section_id' => $section->id,
            'sort_order' => $order,
            'name' => $type,
            'type' => $type,
            'required' => $required,
            'schema' => [
                'label_ar' => $type,
                'validation' => ['required' => $required],
            ],
        ]);
    }

    protected function makeStudent(): User
    {
        $this->userSequence++;

        return User::create([
            'name' => 'Student',
            'email' => "student{$this->userSequence}@example.com",
            'password' => Hash::make('password'),
            'user_type' => 'student',
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
