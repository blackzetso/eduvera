<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = $request->user();

        if ($user->user_type === 'admin') {
            return redirect()->route('admin.dashboard.index');
        }elseif($user->user_type === 'student'){
            return redirect()->route('student.dashboard');
        }

        return redirect()->route('teacher.dashboard.index');
    }
}
