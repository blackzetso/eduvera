<?php

namespace App\Services\Admission;

use App\Models\Admission\AdmissionIntakeLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class AdmissionIntakeGuardService
{
    public function validateRequest(Request $request): void
    {
        $this->rejectHoneypot($request);
        $this->validateCaptchaIfEnabled($request);
        $this->detectSpam($request);
    }

    public function logRequest(
        Request $request,
        string $status,
        ?string $rejectionReason = null,
        ?int $applicationId = null,
    ): void {
        if (! config('admissions_intake.logging.enabled', true)) {
            return;
        }

        if ($status === 'success' && ! config('admissions_intake.logging.log_success', true)) {
            return;
        }

        if ($status === 'rejected' && ! config('admissions_intake.logging.log_rejected', true)) {
            return;
        }

        $email = $request->input('email');
        $phone = $request->input('phone');

        AdmissionIntakeLog::create([
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'endpoint' => $request->path(),
            'status' => $status,
            'rejection_reason' => $rejectionReason,
            'email' => $email,
            'phone' => $phone,
            'application_id' => $applicationId,
            'payload' => $this->sanitizedPayload($request),
            'created_at' => now(),
        ]);
    }

    protected function rejectHoneypot(Request $request): void
    {
        $field = config('admissions_intake.spam.honeypot_field', '_hp_url');

        if ($request->filled($field)) {
            throw ValidationException::withMessages([
                'intake' => ['Request rejected.'],
            ]);
        }
    }

    protected function validateCaptchaIfEnabled(Request $request): void
    {
        if (! config('admissions_intake.captcha.enabled', false)) {
            return;
        }

        $field = config('admissions_intake.captcha.field', 'captcha_token');
        $token = $request->input($field);
        $secret = config('admissions_intake.captcha.secret_key');

        if (! $token || ! $secret) {
            throw ValidationException::withMessages([
                $field => ['CAPTCHA verification required.'],
            ]);
        }

        $provider = config('admissions_intake.captcha.provider', 'recaptcha');

        if ($provider === 'recaptcha') {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);

            if (! $response->json('success')) {
                throw ValidationException::withMessages([
                    $field => ['CAPTCHA verification failed.'],
                ]);
            }
        }
    }

    protected function detectSpam(Request $request): void
    {
        if (! config('admissions_intake.spam.enabled', true)) {
            return;
        }

        $window = now()->subMinutes(
            (int) config('admissions_intake.spam.duplicate_window_minutes', 30)
        );

        $email = $request->input('email');
        $phone = $request->input('phone');

        if ($email) {
            $emailCount = AdmissionIntakeLog::query()
                ->where('email', $email)
                ->where('status', 'success')
                ->where('created_at', '>=', $window)
                ->count();

            if ($emailCount >= (int) config('admissions_intake.spam.max_same_email_per_window', 3)) {
                throw ValidationException::withMessages([
                    'email' => ['Too many requests from this email. Please try again later.'],
                ]);
            }
        }

        if ($phone) {
            $phoneCount = AdmissionIntakeLog::query()
                ->where('phone', $phone)
                ->where('status', 'success')
                ->where('created_at', '>=', $window)
                ->count();

            if ($phoneCount >= (int) config('admissions_intake.spam.max_same_phone_per_window', 3)) {
                throw ValidationException::withMessages([
                    'phone' => ['Too many requests from this phone number. Please try again later.'],
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function sanitizedPayload(Request $request): array
    {
        $data = $request->except([
            'password',
            config('admissions_intake.captcha.field', 'captcha_token'),
            config('admissions_intake.spam.honeypot_field', '_hp_url'),
        ]);

        return array_filter($data, fn ($v) => $v !== null && $v !== '');
    }
}
