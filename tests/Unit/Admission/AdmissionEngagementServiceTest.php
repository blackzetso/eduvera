<?php

namespace Tests\Unit\Admission;

use App\Services\Admission\AdmissionEngagementService;
use App\Support\Admission\AdmissionEngagementChannel;
use App\Support\Admission\AdmissionEngagementStatus;
use App\Support\Admission\AdmissionEngagementType;
use Tests\TestCase;

class AdmissionEngagementServiceTest extends TestCase
{
    public function test_merge_timeline_orders_events_chronologically(): void
    {
        $service = new AdmissionEngagementService;

        $merged = $service->mergeTimeline(
            [
                ['type' => 'note', 'occurred_at' => '2026-01-01T10:00:00+00:00'],
                ['type' => 'stage_change', 'occurred_at' => '2026-01-03T10:00:00+00:00'],
            ],
            [
                ['type' => 'engagement', 'is_engagement' => true, 'occurred_at' => '2026-01-02T10:00:00+00:00'],
            ],
        );

        $this->assertSame('stage_change', $merged[0]['type']);
        $this->assertTrue($merged[1]['is_engagement']);
        $this->assertSame('note', $merged[2]['type']);
    }

    public function test_engagement_type_labels_are_configured(): void
    {
        $this->assertSame('Website Form', AdmissionEngagementType::label(AdmissionEngagementType::WEBSITE_FORM));
        $this->assertSame('bi-building', AdmissionEngagementType::icon(AdmissionEngagementType::CAMPUS_VISIT));
    }

    public function test_engagement_channel_and_status_labels_are_configured(): void
    {
        $this->assertSame('Website', AdmissionEngagementChannel::label(AdmissionEngagementChannel::WEBSITE));
        $this->assertSame('Completed', AdmissionEngagementStatus::label(AdmissionEngagementStatus::COMPLETED));
    }
}
