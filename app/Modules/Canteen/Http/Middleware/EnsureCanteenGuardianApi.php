<?php

namespace App\Modules\Canteen\Http\Middleware;

use App\Modules\Canteen\CanteenModule;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanteenGuardianApi
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! CanteenModule::enabled()) {
            abort(404);
        }

        return $next($request);
    }
}
