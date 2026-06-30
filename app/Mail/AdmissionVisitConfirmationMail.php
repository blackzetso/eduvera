<?php

namespace App\Mail;

use App\Models\Admission\AdmissionApplication;
use App\Models\Website\WebsiteSetting;
use App\Support\Website\WebsiteDefaultsRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdmissionVisitConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AdmissionApplication $application,
        public string $schoolName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Campus Visit Confirmation — '.$this->application->reference_code,
        );
    }

    public function content(): Content
    {
        $visit = $this->application->latestVisit;
        $applicant = $this->application->primaryApplicant;
        $contact = $this->application->primaryContact;

        return new Content(
            htmlString: view('emails.admission-visit-confirmation', [
                'schoolName' => $this->schoolName,
                'referenceCode' => $this->application->reference_code,
                'parentName' => $contact?->name,
                'studentName' => $applicant?->first_name,
                'visitDate' => $visit?->scheduled_date?->format('Y-m-d'),
                'visitTime' => $visit?->scheduled_time,
                'phone' => $contact?->phone,
            ])->render(),
        );
    }

    public static function schoolName(): string
    {
        $info = WebsiteSetting::getValue(
            'school_info',
            WebsiteDefaultsRepository::builtinDefaults()['schoolInfo'] ?? [],
        );

        return is_array($info) ? ($info['name'] ?? config('app.name')) : config('app.name');
    }

    public static function admissionsEmail(): ?string
    {
        $info = WebsiteSetting::getValue(
            'school_info',
            WebsiteDefaultsRepository::builtinDefaults()['schoolInfo'] ?? [],
        );

        if (! is_array($info)) {
            return null;
        }

        return $info['contact']['email']
            ?? $info['topBar']['email']
            ?? null;
    }
}
