<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSocialWorker
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! in_array($request->user()->user_type, ['admin', 'social_worker'], true)) {
            abort(403, 'غير مصرح لك بالوصول.');
        }

        return $next($request);
    }
}
