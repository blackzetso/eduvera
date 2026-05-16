<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures HTML response starts with <!DOCTYPE so the browser uses Standards Mode.
 * Strips any output (BOM, PHP notices, whitespace) that may appear before DOCTYPE.
 */
class EnsureStandardsModeHtml
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$response instanceof Response || $response->getStatusCode() !== 200) {
            return $response;
        }

        $contentType = $response->headers->get('Content-Type', '');
        if (stripos($contentType, 'text/html') === false) {
            return $response;
        }

        $content = $response->getContent();
        if (!is_string($content) || $content === '') {
            return $response;
        }

        $doctype = '<!DOCTYPE';
        $pos = stripos($content, $doctype);
        if ($pos === false) {
            return $response;
        }
        if ($pos === 0) {
            return $response;
        }

        $response->setContent(substr($content, $pos));
        $response->headers->remove('Content-Length');
        return $response;
    }
}
