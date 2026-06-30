<?php

namespace App\Services\Admission;

use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionNote;
use App\Support\Admission\AdmissionEngagementChannel;
use App\Support\Admission\AdmissionEngagementStatus;
use App\Support\Admission\AdmissionEngagementType;
use Illuminate\Support\Facades\Auth;

class AdmissionNoteService
{
    public function __construct(
        protected AdmissionEngagementService $engagements,
    ) {}

    public function store(AdmissionApplication $application, string $content, string $visibility = 'internal'): AdmissionNote
    {
        $note = AdmissionNote::create([
            'admission_application_id' => $application->id,
            'author_user_id' => Auth::id(),
            'visibility' => $visibility,
            'content' => $content,
        ]);

        $this->engagements->record([
            'admission_application_id' => $application->id,
            'type' => AdmissionEngagementType::NOTE,
            'channel' => AdmissionEngagementChannel::INTERNAL,
            'status' => AdmissionEngagementStatus::COMPLETED,
            'subject' => 'ملاحظة داخلية',
            'message' => $content,
            'completed_at' => now(),
            'created_by' => Auth::id(),
            'metadata' => [
                'source_key' => "note:{$note->id}",
                'note_id' => $note->id,
                'visibility' => $visibility,
            ],
        ]);

        return $note;
    }
}
