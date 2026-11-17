<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Vite;

/**
 * Serves the built admin app (public/admin-app/index.html) for every /admin/* page,
 * adding this request's CSP nonce to its scripts (strict Content-Security-Policy).
 */
class SpaController extends Controller
{
    public function __invoke(): Response
    {
        $file = public_path('admin-app/index.html');
        abort_unless(is_file($file), 503, 'Admin app is not built yet. Run: cd admin && pnpm install && pnpm build');

        $nonce = Vite::cspNonce();
        $raw = file_get_contents($file);
        abort_if($raw === false, 503, 'Admin app could not be read. Check file permissions of public/admin-app.');
        $html = preg_replace('/<(script|link)\b/', '<$1 nonce="'.$nonce.'"', $raw);

        return response($html)->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
