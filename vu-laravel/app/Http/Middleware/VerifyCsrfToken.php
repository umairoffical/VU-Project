<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // ACME protocol endpoints (use JWS signatures instead)
        'api/acme/*',
        
        // Webhooks from external services
        'api/webhooks/*',
    ];

    /**
     * Determine if the request has a URI that should pass through CSRF verification.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function inExceptArray($request)
    {
        foreach ($this->except as $except) {
            if ($except !== '/') {
                $except = trim($except, '/');
            }

            if ($request->fullUrlIs($except) || $request->is($except)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add CSRF token to cookies.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function addCookieToResponse($request, $response)
    {
        $config = config('session');

        if ($response instanceof \Illuminate\Http\JsonResponse) {
            $response->withCookie(
                cookie(
                    'XSRF-TOKEN',
                    $request->session()->token(),
                    $config['lifetime'],
                    $config['path'],
                    $config['domain'],
                    $config['secure'] ?? false,
                    false, // Not HTTP only so JavaScript can read it
                    false,
                    $config['same_site'] ?? 'lax'
                )
            );
        }

        return parent::addCookieToResponse($request, $response);
    }
}

