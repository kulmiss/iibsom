@php($locales = app(\App\Support\Localization\Locales::class))
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $locales->supported()[app()->getLocale()]['dir'] ?? 'ltr' }}" data-pwa="{{ feature('pwa') ? 'on' : 'off' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('site.home.title')) · {{ config('app.name') }}</title>
    <meta name="description" content="@yield('description', __('site.meta_description'))">
    <meta name="theme-color" content="#1f6fd1">
    <link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
    <link rel="icon" href="/icons/favicon-32.png" sizes="32x32" type="image/png">
    @feature('pwa')
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    @endfeature
    <link rel="canonical" href="{{ $locales->urlFor(app()->getLocale()) }}">
    @foreach ($locales->enabled() as $code)
        <link rel="alternate" hreflang="{{ $code }}" href="{{ $locales->urlFor($code) }}">
    @endforeach
    @if (count($locales->enabled()) > 1)
        <link rel="alternate" hreflang="x-default" href="{{ $locales->urlFor($locales->default()) }}">
    @endif
    @if (feature('favourites') && feature('ads.card_save'))
        {{-- For the Save buttons on ad cards (card-save.js). Guests: empty (their pages are cached and shared). --}}
        @auth
            <meta name="card-save" content="{{ auth()->user()->favourites()->pluck('ads.id')->implode(',') }}" data-url="{{ lroute('favourite.toggle', ['ad' => 0]) }}" data-token="{{ csrf_token() }}">
        @else
            @php($signIn = feature('login.google') || \App\Http\Controllers\Auth\TestSignInController::available())
            <meta name="card-save" content=""@if ($signIn) data-login="{{ lroute('login') }}"@endif>
        @endauth
    @endif
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('scripts')
</head>
<body class="flex min-h-screen flex-col bg-stone-50 pb-16 text-stone-900 antialiased lg:pb-0">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:m-2 focus:rounded focus:bg-white focus:px-3 focus:py-2">{{ __('site.nav.skip_to_content') }}</a>

    @php($chatUnread = feature('chat') && auth()->check() ? \App\Models\Conversation::unreadCount(auth()->user()) : 0)
    @include('partials.header')

    <main id="main" class="flex-1" data-page>
        @include('partials.flash')
        @yield('content')
    </main>

    @include('partials.footer')
    @include('partials.bottom-nav')
    @auth
        @if (feature('site.welcome_tour') && (session('show_tour') || request()->boolean('tour')))
            @include('partials.welcome-tour')
        @endif
    @endauth
</body>
</html>
