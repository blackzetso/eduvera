<?php

namespace App\Modules\Canteen\Http\Middleware;

use App\Modules\Canteen\Support\CanteenPermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanteenPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! CanteenPermission::isValidPermission($permission)) {
            abort(500, "Invalid canteen permission: {$permission}");
        }

        if (! CanteenPermission::allows($request->user(), $permission)) {
            abort(403);
        }

        return $next($request);
    }
}
