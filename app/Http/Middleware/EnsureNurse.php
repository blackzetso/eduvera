<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNurse
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! in_array($request->user()->user_type, ['admin', 'nurse'], true)) {
            abort(403, 'الوصول مقيد للممرض/الممرضة أو الإدارة فقط.');
        }

        return $next($request);
    }
}
