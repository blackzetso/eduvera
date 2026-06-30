<?php

namespace Tests\Unit\Admission;

use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionDocument;
use App\Services\Admission\AdmissionDocumentService;
use App\Support\Admission\AdmissionDocumentStatus;
use Tests\TestCase;

class AdmissionDocumentSummaryTest extends TestCase
{
    public function test_summary_counts_only_approved_required_documents_as_complete(): void
    {
        $application = new AdmissionApplication(['id' => 1]);
        $application->setRelation('documents', collect([
            new AdmissionDocument(['required' => true, 'status' => AdmissionDocumentStatus::APPROVED]),
            new AdmissionDocument(['required' => true, 'status' => AdmissionDocumentStatus::REVIEW_PENDING]),
            new AdmissionDocument(['required' => true, 'status' => AdmissionDocumentStatus::REUPLOAD_REQUIRED]),
            new AdmissionDocument(['required' => true, 'status' => AdmissionDocumentStatus::NEEDS_UPLOAD]),
            new AdmissionDocument(['required' => false, 'status' => AdmissionDocumentStatus::APPROVED]),
        ]));

        $summary = app(AdmissionDocumentService::class)->summaryFor($application);

        $this->assertSame(4, $summary['required_total']);
        $this->assertSame(1, $summary['required_approved']);
        $this->assertSame(3, $summary['required_incomplete']);
        $this->assertSame(25, $summary['progress_percent']);
        $this->assertSame(4, $summary['progress_total']);
        $this->assertSame(1, $summary['progress_approved']);
        $this->assertFalse($summary['complete']);
        $this->assertSame(1, $summary['required_pending_review']);
        $this->assertSame(1, $summary['required_reupload_required']);
        $this->assertSame(1, $summary['required_needs_upload']);
    }

    public function test_progress_uses_all_documents_when_none_marked_required(): void
    {
        $application = new AdmissionApplication(['id' => 2]);
        $application->setRelation('documents', collect([
            new AdmissionDocument(['required' => false, 'status' => AdmissionDocumentStatus::APPROVED]),
            new AdmissionDocument(['required' => false, 'status' => AdmissionDocumentStatus::REVIEW_PENDING]),
            new AdmissionDocument(['required' => false, 'status' => AdmissionDocumentStatus::REVIEW_PENDING]),
        ]));

        $summary = app(AdmissionDocumentService::class)->summaryFor($application);

        $this->assertSame(0, $summary['required_total']);
        $this->assertSame(3, $summary['progress_total']);
        $this->assertSame(1, $summary['progress_approved']);
        $this->assertSame(33, $summary['progress_percent']);
    }

    public function test_readiness_presentation_describes_decision_blockers(): void
    {
        $application = new AdmissionApplication(['id' => 1]);
        $application->setRelation('documents', collect([
            new AdmissionDocument(['required' => true, 'status' => AdmissionDocumentStatus::APPROVED]),
            new AdmissionDocument(['required' => true, 'status' => AdmissionDocumentStatus::REJECTED]),
            new AdmissionDocument(['required' => true, 'status' => AdmissionDocumentStatus::REVIEW_PENDING]),
            new AdmissionDocument(['required' => true, 'status' => AdmissionDocumentStatus::NEEDS_UPLOAD]),
        ]));

        $service = app(AdmissionDocumentService::class);
        $decision = $service->readinessPresentation($application, 'decision');
        $conversion = $service->readinessPresentation($application, 'conversion');

        $this->assertSame('المستندات: 1 / 4 معتمد', $decision['label']);
        $this->assertSame('3 مستندات تمنع اتخاذ القرار', $decision['detail']);
        $this->assertSame('3 مستندات تمنع التحويل', $conversion['detail']);
    }
}
