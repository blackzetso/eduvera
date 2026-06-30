<?php

namespace Tests\Unit\Admission;

use App\Support\Admission\AdmissionDocumentStatus;
use Tests\TestCase;

class AdmissionDocumentStatusTest extends TestCase
{
    public function test_only_approved_does_not_block_readiness(): void
    {
        $this->assertFalse(AdmissionDocumentStatus::blocksReadiness(AdmissionDocumentStatus::APPROVED));
        $this->assertTrue(AdmissionDocumentStatus::blocksReadiness(AdmissionDocumentStatus::REVIEW_PENDING));
        $this->assertTrue(AdmissionDocumentStatus::blocksReadiness(AdmissionDocumentStatus::REUPLOAD_REQUIRED));
        $this->assertTrue(AdmissionDocumentStatus::blocksReadiness(AdmissionDocumentStatus::REJECTED));
        $this->assertTrue(AdmissionDocumentStatus::blocksReadiness(AdmissionDocumentStatus::NEEDS_UPLOAD));
    }

    public function test_arabic_labels_are_configured(): void
    {
        $this->assertSame('معتمد', AdmissionDocumentStatus::label(AdmissionDocumentStatus::APPROVED));
        $this->assertSame('يحتاج إعادة رفع', AdmissionDocumentStatus::label(AdmissionDocumentStatus::REUPLOAD_REQUIRED));
        $this->assertSame('مُقدَّم', AdmissionDocumentStatus::label(AdmissionDocumentStatus::LEGACY_SUBMITTED));
        $this->assertSame('قيد المراجعة', AdmissionDocumentStatus::label(AdmissionDocumentStatus::LEGACY_PENDING));
    }

    public function test_legacy_submitted_is_reviewable(): void
    {
        $this->assertContains(AdmissionDocumentStatus::LEGACY_SUBMITTED, AdmissionDocumentStatus::reviewable());
    }
}
