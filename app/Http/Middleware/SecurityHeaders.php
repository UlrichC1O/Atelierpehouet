<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers for every web response (docs/ARCHITECTURE.md §12).
 *
 * The Content-Security-Policy is added to HTML responses only, in report-only mode
 * while APP_DEBUG is on, and never replaces a policy a controller already set
 * (e.g. the stricter one of the art endpoint). Other headers are never overwritten.
 */
final class SecurityHeaders
{
    public const CONTENT_SECURITY_POLICY = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; "
        ."img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; object-src 'none'; "
        ."base-uri 'self'; form-action 'self'; frame-ancestors 'self'";

    /** @var array<string, string> */
    public const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Permissions-Policy' => 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        foreach (self::HEADERS as $name => $value) {
            if (! $headers->has($name)) {
                $headers->set($name, $value);
            }
        }

        if ($this->isHtml($response)
            && ! $headers->has('Content-Security-Policy')
            && ! $headers->has('Content-Security-Policy-Report-Only')) {
            $headers->set(
                config('app.debug') ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy',
                self::CONTENT_SECURITY_POLICY,
            );
        }

        return $response;
    }

    /** Responses without a Content-Type yet are prepared as text/html by Symfony. */
    private function isHtml(Response $response): bool
    {
        $type = $response->headers->get('Content-Type');

        return $type === null || str_starts_with(strtolower(trim($type)), 'text/html');
    }
}
