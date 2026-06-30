<?php

namespace Tests\Unit\Admission;

use App\Support\Admission\AdmissionVisitAttention;
use Tests\TestCase;

class AdmissionVisitAttentionTest extends TestCase
{
    public function test_needs_follow_up_requires_positive_outcome_and_three_days(): void
    {
        $this->assertTrue(AdmissionVisitAttention::needsFollowUp([
            'outcome' => 'interested',
            'pipeline_stage' => 'campus_visit',
            'application_status' => 'open',
            'scheduled_date' => now()->subDays(5)->toDateString(),
        ]));

        $this->assertFalse(AdmissionVisitAttention::needsFollowUp([
            'outcome' => 'not_interested',
            'pipeline_stage' => 'campus_visit',
            'application_status' => 'open',
            'scheduled_date' => now()->subDays(5)->toDateString(),
        ]));
    }

    public function test_enrich_row_adds_server_driven_fields(): void
    {
        $enriched = AdmissionVisitAttention::enrichRow([
            'status' => 'confirmed',
            'outcome' => 'interested',
            'pipeline_stage' => 'campus_visit',
            'application_status' => 'open',
            'scheduled_date' => now()->subDays(4)->toDateString(),
        ]);

        $this->assertArrayHasKey('color_key', $enriched);
        $this->assertArrayHasKey('needs_follow_up', $enriched);
        $this->assertArrayHasKey('follow_up_priority', $enriched);
        $this->assertArrayHasKey('days_since_visit', $enriched);
    }
}
