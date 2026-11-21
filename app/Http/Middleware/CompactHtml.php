<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Smaller pages (PR 112, switch "performance.compact_html"): removes the indentation and empty lines that
 * Blade leaves in HTML (about a fifth of a page). The page looks exactly the same: a line break is kept
 * wherever there was one, and <pre>, <textarea> and <script> are never touched.
 * Helps most on hosts that do not compress pages (and on slow phones either way).
 */
class CompactHtml
{
    private const KEEP = '#(<pre\b.*?</pre>|<textarea\b.*?</textarea>|<script\b.*?</script>)#is';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse
            || ! str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')
            || ! feature('performance.compact_html')) {
            return $response;
        }

        $html = $response->getContent();
        if (! is_string($html) || $html === '') {
            return $response;
        }

        $parts = preg_split(self::KEEP, $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $response;
        }
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) { // outside the kept blocks
                $parts[$i] = (string) preg_replace(['/\n[ \t]+/', '/\n{2,}/'], ["\n", "\n"], $part);
            }
        }
        $response->setContent(implode('', $parts));

        return $response;
    }
}
