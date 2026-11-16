<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers for every web response, including a strict
 * Content-Security-Policy with a per-request nonce for scripts.
 * Use the nonce in Blade with: <script nonce="{{ Vite::cspNonce() }}">
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Str::random(32);
        Vite::useCspNonce($nonce);

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Content-Security-Policy', $this->csp($nonce));

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $headers->remove('X-Powered-By');

        return $response;
    }

    private function csp(string $nonce): string
    {
        $images = array_filter(["'self'", 'data:', 'blob:', config('filesystems.disks.r2.url') ?: null]);

        $policy = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", "'strict-dynamic'"],
            'style-src' => ["'self'", "'unsafe-inline'"],
            'img-src' => $images,
            'font-src' => ["'self'"],
            'connect-src' => ["'self'"],
            'frame-ancestors' => ["'none'"],
            'form-action' => ["'self'", 'https://accounts.google.com'],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
        ];

        if (app()->environment('local')) {
            // Vite dev server (npm run dev) for live reload.
            $policy['script-src'][] = 'http://localhost:5173';
            $policy['style-src'][] = 'http://localhost:5173';
            $policy['connect-src'][] = 'ws://localhost:5173';
        }

        return collect($policy)
            ->map(fn (array $sources, string $directive) => $directive.' '.implode(' ', $sources))
            ->implode('; ');
    }
}
