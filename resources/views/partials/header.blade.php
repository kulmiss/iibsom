<header class="sticky top-0 z-20 border-b border-stone-200 bg-white/95 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center gap-3 px-4 py-3">
        <a href="{{ lroute('home') }}" class="text-xl font-bold tracking-tight text-brand-700" aria-label="{{ config('app.name') }} – {{ __('site.nav.home') }}">
            {{-- Lowercase so the first "I" is not read as "l". --}}
            {{ \Illuminate\Support\Str::lower(config('app.name')) }}
        </a>

        <nav class="ml-auto flex items-center gap-2" aria-label="Main">
            @include('partials.language-switcher')

            @feature('ads.posting')
                <a href="#" class="btn-primary">
                    <svg aria-hidden="true" viewBox="0 0 20 20" class="size-4 fill-current"><path d="M10 3a1 1 0 0 1 1 1v5h5a1 1 0 1 1 0 2h-5v5a1 1 0 1 1-2 0v-5H4a1 1 0 1 1 0-2h5V4a1 1 0 0 1 1-1Z"/></svg>
                    <span>{{ __('site.nav.post_ad') }}</span>
                </a>
            @endfeature
        </nav>
    </div>
</header>
