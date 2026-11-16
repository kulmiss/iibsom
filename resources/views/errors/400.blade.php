{{-- Stand-alone (no layout): this page can appear before the session starts. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>iibsom</title>
    <style>body{font-family:system-ui,sans-serif;background:#fafaf9;color:#1c1917;margin:0;display:flex;min-height:100vh;align-items:center;justify-content:center;text-align:center;padding:1rem}a{display:inline-block;margin-top:1.5rem;background:#1f6fd1;color:#fff;padding:.6rem 1.2rem;border-radius:.5rem;text-decoration:none;font-weight:600}</style>
</head>
<body>
<main>
    <p style="font-size:1.6rem;font-weight:800;color:#1f6fd1">iibsom</p>
    <p>{{ $exception->getMessage() ?: __('site.errors.bad_text') }}</p>
    <a href="{{ url('/') }}">{{ __('site.errors.back_home') }}</a>
</main>
</body>
</html>
