<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDovaKnowledgeAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->user_type !== 'admin') {
            abort(403, 'غير مصرح لك بالوصول إلى مركز معرفة دوفا.');
        }

        $role = $user->role ?? 'admin';
        $allowed = config('dova-knowledge.allowed_roles', ['super_admin', 'admin', 'content_manager']);

        if ($role && ! in_array($role, $allowed, true)) {
            abort(403, 'دورك الحالي لا يسمح بالوصول إلى مركز معرفة دوفا.');
        }

        return $next($request);
    }
}
