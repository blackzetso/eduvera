<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCardReader
{
    public function handle(Request $request, Closure $next): Response
    {
        $deviceId = $request->input('device_id') ?? $request->header('X-Device-Id');

        if (! $deviceId) {
            abort(401, 'معرّف الجهاز مطلوب.');
        }

        $request->attributes->set('card_reader_device_id', $deviceId);

        return $next($request);
    }
}
