{{--
  Stand-alone error page (no layout, no database, no session), in the visitor's language.
  Used for 403, 419, 429, 500 and 503, so it still works when something else is broken.
--}}
@php
    // The error can come before the language was set (for example "too many tries"): the address decides.
    $first = request()->segment(1);
    if (is_string($first) && $first !== config('iibsom.default_locale') && array_key_exists($first, (array) config('iibsom.locales'))) {
        app()->setLocale($first);
    }
    $title = __('site.errors.'.$key.'_title');
    $text = __('site.errors.'.$key.'_text');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · iibsom</title>
    <style>body{font-family:system-ui,sans-serif;background:#fafaf9;color:#1c1917;margin:0;display:flex;min-height:100vh;align-items:center;justify-content:center;text-align:center;padding:1rem}main{max-width:28rem}h1{font-size:1.4rem;margin:.5rem 0}p{color:#57534e;line-height:1.5}a{display:inline-block;margin-top:1.5rem;background:#1f6fd1;color:#fff;padding:.7rem 1.3rem;border-radius:.5rem;text-decoration:none;font-weight:600}</style>
</head>
<body>
<main>
    <p style="font-size:1.6rem;font-weight:800;color:#1f6fd1;margin:0">iibsom</p>
    <h1>{{ $title }}</h1>
    <p>{{ $text }}</p>
    <a href="{{ app()->getLocale() === config('iibsom.default_locale') ? url('/') : url('/'.app()->getLocale()) }}">{{ __('site.errors.back_home') }}</a>
</main>
</body>
</html>
