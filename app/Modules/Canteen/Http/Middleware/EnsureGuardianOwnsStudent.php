<?php

namespace App\Modules\Canteen\Http\Middleware;

use App\Models\User;
use App\Modules\Canteen\Exceptions\GuardianAccessDeniedException;
use App\Modules\Canteen\Integration\Contracts\GuardianIntegrationPort;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGuardianOwnsStudent
{
    public function __construct(protected GuardianIntegrationPort $guardians) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guardian = $request->user();

        if (! $guardian || $guardian->user_type !== 'guardian') {
            abort(403, 'Guardian access required.');
        }

        $student = $request->route('student');

        if (! $student instanceof User) {
            $student = User::query()->students()->find($student);
        }

        if (! $student) {
            abort(404, 'Student not found.');
        }

        try {
            $this->guardians->assertGuardianLinkedToStudent($guardian, $student);
        } catch (GuardianAccessDeniedException $e) {
            abort(403, $e->getMessage());
        }

        $request->attributes->set('canteen_student', $student);

        return $next($request);
    }
}
