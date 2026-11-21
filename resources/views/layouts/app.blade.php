@php($locales = app(\App\Support\Localization\Locales::class))
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $locales->supported()[app()->getLocale()]['dir'] ?? 'ltr' }}" data-pwa="{{ feature('pwa') ? 'on' : 'off' }}" @unless (feature('site.dark_mode')) data-theme="light" @endunless>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('site.home.title')) · {{ config('app.name') }}</title>
    <meta name="description" content="@yield('description', __('site.meta_description'))">
    <meta name="theme-color" content="{{ \App\Support\Theme\SiteColors::current() }}">
    {{-- "js" on <html> before the page draws: parts that only work with JavaScript take their place at once
         (no jump when the script runs, PR 80). --}}
    <script nonce="{{ Vite::cspNonce() }}">document.documentElement.classList.add('js')</script>
    @if (feature('site.dark_mode'))
        {{-- Light/dark chosen in the footer: applied before the page draws (no flash). --}}
        <script nonce="{{ Vite::cspNonce() }}">(function(){var t;try{t=localStorage.getItem('iibsom-theme')}catch(e){}if(t!=='light'&&t!=='dark')t=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';document.documentElement.dataset.theme=t})()</script>
    @endif
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
            @php($signIn = sign_in_open())
            <meta name="card-save" content=""@if ($signIn) data-login="{{ lroute('login') }}"@endif>
        @endauth
        {{-- The button's words, once per page instead of on every card (PR 112: smaller pages). ":" + the ad title is added by card-save.js. --}}
        <meta name="card-save-labels" data-on="{{ __('ads.saved.unsave_label') }}" data-off="{{ __('ads.saved.save_label') }}">
    @endif
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if (feature('site.custom_color') && ($siteColorCss = \App\Support\Theme\SiteColors::css(setting('site.brand_color'))) !== '')
        {{-- The website colour chosen in Admin → Settings → Appearance (PR 88). Made only from a checked #rrggbb. --}}
        <style data-site-color>{!! $siteColorCss !!}</style>
    @endif
    @stack('scripts')
</head>
<body class="flex min-h-screen flex-col bg-stone-50 pb-16 text-stone-900 antialiased lg:pb-0">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:m-2 focus:rounded focus:bg-(--surface) focus:px-3 focus:py-2">{{ __('site.nav.skip_to_content') }}</a>

    @php($chatUnread = feature('chat') && auth()->check() ? \App\Models\Conversation::unreadCount(auth()->user()) : 0)
    @php($savedCount = feature('favourites') && feature('account.saved_count') && auth()->check() ? auth()->user()->favourites()->count() : null)
    @php($bellUnread = feature('notifications') && auth()->check() ? \App\Support\Notify\Inbox::unread(auth()->user()) : 0)
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
