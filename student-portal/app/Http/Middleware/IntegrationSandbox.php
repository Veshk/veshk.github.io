<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IntegrationSandbox
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('course_integration.sandbox')) {
            abort_if($request->is('register', 'applications', 'applications/*', 'demo', 'demo/*'), 404);
        }
        $response = $next($request);
        if (config('course_integration.sandbox') || $request->is('password/*')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            $response->headers->set('Referrer-Policy', 'no-referrer');
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
