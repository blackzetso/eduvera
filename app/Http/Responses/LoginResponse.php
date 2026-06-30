<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = $request->user();

        return match ($user->user_type) {
            'admin' => redirect()->route('admin.dashboard.index'),
            'student' => redirect()->route('student.dashboard'),
            'guardian' => redirect()->route('guardian.dashboard'),
            'teacher' => redirect()->route('teacher.dashboard.index'),
            'control_staff' => redirect()->route('admin.attendances.dashboard'),
            'social_worker', 'nurse' => redirect()->route('admin.attendances.alerts'),
            'department_head' => redirect()->route('department-plan.index'),
            default => redirect()->route('home'),
        };
    }
}
