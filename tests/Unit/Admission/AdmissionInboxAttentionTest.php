<?php

namespace Tests\Unit\Admission;

use App\Support\Admission\AdmissionDecision;
use App\Support\Admission\AdmissionInboxAttention;
use App\Support\Admission\AdmissionStage;
use Tests\TestCase;

class AdmissionInboxAttentionTest extends TestCase
{
    public function test_ready_for_conversion_requires_accepted_application_open(): void
    {
        $this->assertTrue(AdmissionInboxAttention::isReadyForConversion([
            'decision' => AdmissionDecision::ACCEPTED,
            'pipeline_stage' => AdmissionStage::APPLICATION,
            'status' => 'open',
        ]));

        $this->assertFalse(AdmissionInboxAttention::isReadyForConversion([
            'decision' => AdmissionDecision::ACCEPTED,
            'pipeline_stage' => AdmissionStage::CAMPUS_VISIT,
            'status' => 'open',
        ]));
    }

    public function test_needs_follow_up_includes_unassigned_and_stale(): void
    {
        $this->assertTrue(AdmissionInboxAttention::needsFollowUp([
            'status' => 'open',
            'assigned_to' => null,
            'created_at' => now()->toIso8601String(),
        ]));

        $this->assertTrue(AdmissionInboxAttention::needsFollowUp([
            'status' => 'open',
            'assigned_to' => ['id' => 1],
            'created_at' => now()->subDays(10)->toIso8601String(),
        ]));
    }

    public function test_priority_meta_scores_ready_for_conversion_highest(): void
    {
        $meta = AdmissionInboxAttention::priorityMeta([
            'decision' => AdmissionDecision::ACCEPTED,
            'pipeline_stage' => AdmissionStage::APPLICATION,
            'status' => 'open',
            'assigned_to' => ['id' => 1],
            'created_at' => now()->toIso8601String(),
        ]);

        $this->assertGreaterThanOrEqual(100, $meta['score']);
        $this->assertSame('high', $meta['level']);
    }
}
