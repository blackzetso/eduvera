<?php

namespace App\Modules\Canteen\Http\Middleware;

use App\Modules\Canteen\CanteenModule;
use App\Modules\Canteen\Support\CanteenPermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanteenModuleEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! CanteenModule::enabled()) {
            abort(404);
        }

        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        if ($user->user_type !== 'admin' && CanteenPermission::forUser($user) === []) {
            abort(403, 'You do not have access to the Canteen module.');
        }

        return $next($request);
    }
}
