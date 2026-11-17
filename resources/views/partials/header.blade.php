<header class="sticky top-0 z-20 bg-brand-700 text-white">
    <div class="mx-auto flex max-w-6xl items-center gap-2 px-4 py-2.5 sm:gap-3">
        <a href="{{ lroute('home') }}" class="text-2xl font-extrabold tracking-tight" aria-label="{{ config('app.name') }} – {{ __('site.nav.home') }}">
            {{-- Lowercase so the first "I" is not read as "l". --}}
            {{ \Illuminate\Support\Str::lower(config('app.name')) }}
        </a>

        <nav class="ml-auto flex items-center gap-1.5 sm:gap-2" aria-label="Main">
            @include('partials.language-switcher')

            {{-- Phase 2 features: hidden until their switch is ON. --}}
            @feature('favourites')
                <a href="{{ lroute('saved') }}" class="icon-btn hidden lg:flex" title="{{ __('site.nav.saved') }}">{{ icon('bookmark') }}<span class="sr-only">{{ __('site.nav.saved') }}</span></a>
            @endfeature
            @feature('chat')
                <a href="{{ lroute('chat') }}" class="icon-btn relative hidden lg:flex" title="{{ __('site.nav.messages') }}">{{ icon('message-circle') }}<span class="sr-only">{{ __('site.nav.messages') }}</span>@if (($chatUnread ?? 0) > 0)<span class="absolute end-1 top-1 size-2.5 rounded-full bg-accent-400 ring-2 ring-brand-700" aria-label="{{ __('chat.new') }}"></span>@endif</a>
            @endfeature
            @feature('saved_searches')
                <a href="{{ lroute('searches') }}" class="icon-btn" title="{{ __('site.nav.alerts') }}">{{ icon('bell') }}<span class="sr-only">{{ __('site.nav.alerts') }}</span></a>
            @endfeature

            @auth
                <a href="{{ lroute('account') }}" class="hidden size-9 items-center justify-center rounded-full bg-white font-bold text-brand-700 lg:flex" title="{{ __('site.nav.my_account') }}">
                    {{ auth()->user()->initial() }}<span class="sr-only">{{ __('site.nav.my_account') }}</span>
                </a>
            @else
                @feature('login.google')
                    <a href="{{ lroute('login') }}" class="icon-btn hidden lg:flex" title="{{ __('site.nav.sign_in') }}">{{ icon('user') }}<span class="sr-only">{{ __('site.nav.sign_in') }}</span></a>
                @endfeature
            @endauth

            @if (feature('ads.posting') && (auth()->check() || feature('login.google')))
                <a href="{{ lroute('post') }}" class="btn-sell hidden lg:inline-flex">{{ __('site.nav.sell') }}</a>
            @endif
        </nav>
    </div>
</header>
