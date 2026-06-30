<?php

namespace App\Http\Middleware;

use App\Services\Admission\AdmissionIntakeGuardService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecureAdmissionIntake
{
    public function __construct(
        protected AdmissionIntakeGuardService $guard,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $this->guard->validateRequest($request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->guard->logRequest($request, 'rejected', 'validation_failed');

            throw $e;
        }

        return $next($request);
    }
}
