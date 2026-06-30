<?php

namespace Tests\Unit\Admission;

use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionDocument;
use App\Models\Admission\AdmissionDocumentDefinition;
use App\Services\Admission\AdmissionDocumentDefinitionService;
use App\Services\Admission\AdmissionDocumentService;
use App\Support\Admission\AdmissionDocumentStatus;
use Tests\TestCase;

class AdmissionDocumentDefinitionTest extends TestCase
{
    public function test_document_service_resolves_definition_service_from_container(): void
    {
        $service = app(AdmissionDocumentService::class);

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('definitions');
        $property->setAccessible(true);

        $this->assertInstanceOf(AdmissionDocumentDefinitionService::class, $property->getValue($service));
    }

    public function test_definition_model_exposes_form_builder_source_constant(): void
    {
        $this->assertSame('settings', AdmissionDocumentDefinition::SOURCE_SETTINGS);
        $this->assertSame('form_builder', AdmissionDocumentDefinition::SOURCE_FORM_BUILDER);
    }

    public function test_config_declares_document_definition_sources(): void
    {
        $sources = config('admissions.document_definition_sources');

        $this->assertArrayHasKey('settings', $sources);
        $this->assertArrayHasKey('form_builder', $sources);
    }

    public function test_progress_still_counts_only_required_approved_documents(): void
    {
        $application = new AdmissionApplication(['id' => 1]);
        $application->setRelation('documents', collect([
            new AdmissionDocument(['required' => true, 'status' => AdmissionDocumentStatus::APPROVED]),
            new AdmissionDocument(['required' => true, 'status' => AdmissionDocumentStatus::REVIEW_PENDING]),
            new AdmissionDocument(['required' => false, 'status' => AdmissionDocumentStatus::APPROVED]),
        ]));

        $summary = app(AdmissionDocumentService::class)->summaryFor($application);

        $this->assertSame(2, $summary['required_total']);
        $this->assertSame(1, $summary['progress_approved']);
        $this->assertSame(2, $summary['progress_total']);
        $this->assertFalse($summary['complete']);
    }
}
