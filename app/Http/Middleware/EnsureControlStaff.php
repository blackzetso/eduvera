<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureControlStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! in_array($request->user()->user_type, ['admin', 'control_staff'], true)) {
            abort(403, 'الوصول مقيد لموظفي الكنترول أو الإدارة فقط.');
        }

        return $next($request);
    }
}
