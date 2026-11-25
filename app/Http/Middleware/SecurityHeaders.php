<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;

/**
 * Security headers on every response. Inline <script> tags must carry the nonce
 * (@nonce-less scripts are blocked). Alpine.js needs 'unsafe-eval'.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $nonce = Vite::useCspNonce();
        $response = $next($request);
        $dev = app()->environment('local') && file_exists(public_path('hot'));
        $viteDev = $dev ? ' http://localhost:5173 ws://localhost:5173 http://[::1]:5173 ws://[::1]:5173' : '';

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'".$viteDev,
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net".$viteDev,
            "font-src 'self' https://fonts.bunny.net data:",
            "img-src 'self' data: blob:",
            "media-src 'self' blob:",
            "connect-src 'self'".$viteDev,
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);
        $h = $response->headers;
        if ($h->get('X-SmartSchool-Sandbox')) {
            // Teacher-made class content: runs in a unique, empty origin (no cookies, no access to the app),
            // may only be framed by SmartSchool itself, and may load media/embeds (e.g. H5P, YouTube) from anywhere.
            $h->remove('X-SmartSchool-Sandbox');
            $h->set('Content-Security-Policy', "sandbox allow-scripts allow-popups allow-forms allow-modals; default-src * data: blob: 'unsafe-inline' 'unsafe-eval'; frame-ancestors 'self'; form-action 'none'; base-uri 'none'");
            $h->set('X-Frame-Options', 'SAMEORIGIN');
            $h->set('X-Content-Type-Options', 'nosniff');
            $h->set('Referrer-Policy', 'no-referrer');

            return $response;
        }
        $h->set('Content-Security-Policy', $csp);
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'DENY');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        $h->remove('X-Powered-By');

        return $response;
    }
}
