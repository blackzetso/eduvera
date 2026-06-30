<?php

namespace Tests\Unit\FormBuilder;

use App\Models\FormSubmission;
use App\Support\FormBuilder\FormRuntimeContext;
use App\Support\FormBuilder\FormSubmissionFinalizedPayload;
use App\Support\FormBuilder\FormSubmissionSnapshot;
use App\Support\FormBuilder\FormSubmissionStatus;
use Tests\TestCase;

class FormSubmissionFinalizedPayloadTest extends TestCase
{
    public function test_builds_frozen_event_contract_shape(): void
    {
        $submission = new FormSubmission([
            'form_id' => 7,
            'status' => FormSubmissionStatus::SUBMITTED,
            'locale' => 'ar',
            'data' => FormSubmissionSnapshot::attach(
                ['fld_1' => 'Sara'],
                [
                    'form_id' => 7,
                    'form_version' => 3,
                    'snapshot_hash' => 'abc123',
                    'captured_at' => now()->toIso8601String(),
                ],
                ['channel' => 'public'],
            ),
        ]);
        $submission->id = 42;
        $submission->exists = true;

        $payload = FormSubmissionFinalizedPayload::fromSubmission(
            $submission,
            FormRuntimeContext::anonymous('ar', '127.0.0.1'),
        );

        $array = $payload->toArray();

        $this->assertSame('form_submission.finalized', $array['event']);
        $this->assertSame('1.0.0', $array['schema_version']);
        $this->assertSame(42, $array['submission_id']);
        $this->assertSame(7, $array['form_id']);
        $this->assertSame(3, $payload->snapshotFormVersion());
        $this->assertArrayHasKey('correlation_id', $array);
        $this->assertArrayHasKey('data', $array);
    }

    public function test_should_emit_only_on_first_finalized_transition(): void
    {
        $this->assertTrue(FormSubmissionFinalizedPayload::shouldEmit(FormSubmissionStatus::SUBMITTED));
        $this->assertTrue(FormSubmissionFinalizedPayload::shouldEmit(
            FormSubmissionStatus::APPROVED,
            FormSubmissionStatus::UNDER_REVIEW,
        ));
        $this->assertFalse(FormSubmissionFinalizedPayload::shouldEmit(FormSubmissionStatus::UNDER_REVIEW));
        $this->assertFalse(FormSubmissionFinalizedPayload::shouldEmit(
            FormSubmissionStatus::SUBMITTED,
            FormSubmissionStatus::SUBMITTED,
        ));
    }
}
