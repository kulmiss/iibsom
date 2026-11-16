<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Text with broken characters (invalid UTF-8 bytes) is refused with 400 before
 * it reaches validation, search or the database (MySQL would reject it and
 * crash the page). Real phones and browsers always send valid UTF-8.
 */
class RejectInvalidUtf8
{
    public function handle(Request $request, Closure $next): Response
    {
        $valid = true;
        $check = function (mixed $value) use (&$check, &$valid): void {
            if (is_array($value)) {
                foreach ($value as $key => $item) {
                    if (is_string($key) && ! mb_check_encoding($key, 'UTF-8')) {
                        $valid = false;
                    }
                    $check($item);
                }
            } elseif (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                $valid = false;
            }
        };
        $check($request->query->all());
        $check($request->request->all());
        $check($request->path());

        abort_unless($valid, 400, __('site.errors.bad_text'));

        return $next($request);
    }
}
