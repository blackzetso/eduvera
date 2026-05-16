<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || $request->user()->user_type !== 'admin') {
            abort(403, 'غير مصرح لك بالوصول.');
        }

        return $next($request);
    }
}
