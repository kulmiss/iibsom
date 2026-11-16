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
                <a href="#" class="icon-btn hidden lg:flex" title="{{ __('site.nav.saved') }}">{{ icon('bookmark') }}<span class="sr-only">{{ __('site.nav.saved') }}</span></a>
            @endfeature
            @feature('chat')
                <a href="#" class="icon-btn hidden lg:flex" title="{{ __('site.nav.messages') }}">{{ icon('message-circle') }}<span class="sr-only">{{ __('site.nav.messages') }}</span></a>
            @endfeature
            @feature('saved_searches')
                <a href="#" class="icon-btn" title="{{ __('site.nav.alerts') }}">{{ icon('bell') }}<span class="sr-only">{{ __('site.nav.alerts') }}</span></a>
            @endfeature

            @feature('login.google')
                <a href="#" class="icon-btn hidden lg:flex" title="{{ __('site.nav.sign_in') }}">{{ icon('user') }}<span class="sr-only">{{ __('site.nav.sign_in') }}</span></a>
            @endfeature

            @feature('ads.posting')
                <a href="#" class="btn-sell hidden lg:inline-flex">{{ __('site.nav.sell') }}</a>
            @endfeature
        </nav>
    </div>
</header>
