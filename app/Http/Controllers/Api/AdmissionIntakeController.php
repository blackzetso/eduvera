<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\AdmissionVisitConfirmationMail;
use App\Services\Admission\AdmissionIntakeGuardService;
use App\Services\Admission\AdmissionIntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdmissionIntakeController extends Controller
{
    public function __construct(
        protected AdmissionIntakeService $intake,
        protected AdmissionIntakeGuardService $guard,
    ) {}

    public function visit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parentName' => 'nullable|string|max:255',
            'parent_name' => 'nullable|string|max:255',
            'studentName' => 'nullable|string|max:255',
            'student_name' => 'nullable|string|max:255',
            'currentGrade' => 'nullable|string|max:100',
            'current_grade' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:255',
            'visitDate' => 'nullable|date',
            'visit_date' => 'nullable|date',
            'visitTime' => 'nullable|string|max:20',
            'visit_time' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:5000',
            'formId' => 'nullable|string|max:100',
        ]);

        $parentName = $validated['parentName'] ?? $validated['parent_name'] ?? null;
        $studentName = $validated['studentName'] ?? $validated['student_name'] ?? null;

        if (! $parentName && ! $studentName) {
            return response()->json([
                'message' => 'Parent name or student name is required.',
            ], 422);
        }

        $application = $this->intake->processVisitRequest($validated);
        $contact = $application->primaryContact;
        $applicant = $application->primaryApplicant;
        $visit = $application->latestVisit;
        $parentEmail = $contact?->email;
        $schoolEmail = AdmissionVisitConfirmationMail::admissionsEmail();
        $emailSent = $parentEmail
            ? $this->intake->sendConfirmationEmail($application, $parentEmail)
            : false;

        $this->guard->logRequest($request, 'success', null, $application->id);

        return response()->json([
            'message' => 'Visit request received.',
            'reference_code' => $application->reference_code,
            'application_id' => $application->id,
            'visit' => [
                'scheduled_date' => $visit?->scheduled_date?->format('Y-m-d'),
                'scheduled_time' => $visit?->scheduled_time,
                'status' => $visit?->status ?? 'requested',
            ],
            'contact' => [
                'parent_name' => $contact?->name,
                'student_name' => $applicant?->first_name,
                'current_grade' => $applicant?->current_grade_label,
                'phone' => $contact?->phone,
                'email' => $parentEmail,
            ],
            'confirmation' => [
                'email_sent' => $emailSent,
                'sent_to' => $parentEmail,
                'school_receiver_email' => $schoolEmail,
            ],
        ], 201);
    }
}
