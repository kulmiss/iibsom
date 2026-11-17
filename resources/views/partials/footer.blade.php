{{--
  Site footer. Big footer (switch "site.new_footer"): the coast picture, link columns, social pages, languages.
  Links and social pages: Admin → Settings → General → Footer. Columns fold on phones (footer.js);
  without JavaScript they simply stay open. OFF: the old short row of links.
--}}
@feature('site.new_footer')
    @php
        $footer = app(\App\Support\Site\Footer::class);
        $locales = app(\App\Support\Localization\Locales::class);
        $supportEmail = (string) setting('site.support_email');
        $about = array_filter([
            'about' => $footer->on('about') ? [lroute('page', ['slug' => 'about']), __('site.footer.about')] : null,
            'terms' => $footer->on('terms') ? [lroute('page', ['slug' => 'terms']), __('site.footer.terms')] : null,
            'privacy' => $footer->on('privacy') ? [lroute('page', ['slug' => 'privacy']), __('site.footer.privacy')] : null,
            'cookies' => $footer->on('cookies') ? [lroute('page', ['slug' => 'cookies']), __('site.footer.cookies')] : null,
            'copyright' => $footer->on('copyright') ? [lroute('page', ['slug' => 'copyright']), __('site.footer.copyright')] : null,
        ]);
        $support = array_filter([
            'contact' => $footer->on('contact') && $supportEmail !== '' ? ['mailto:'.$supportEmail, __('site.footer.contact')] : null,
            'safety' => $footer->on('safety') ? [lroute('page', ['slug' => 'safety']), __('site.footer.safety')] : null,
            'help' => $footer->on('help') && feature('help.centre') ? [lroute('help'), __('site.footer.faq')] : null,
        ]);
        $popularCats = $footer->on('popular') ? $footer->popularCategories() : [];
        $popularCities = $footer->on('popular') ? $footer->popularCities() : [];
        $socials = $footer->socials();
        $columns = array_filter([
            'about' => $about ? [__('site.footer.col_about'), $about] : null,
            'support' => $support ? [__('site.footer.col_support'), $support] : null,
        ]);
        $linkClass = 'hover:text-white hover:underline';
    @endphp
    <div class="mt-12 -mb-16 lg:mb-0" data-site-footer>
        @include('partials.footer-skyline')
        <footer class="bg-brand-950 text-brand-200">
            <div class="mx-auto max-w-6xl px-4 pt-6 pb-22 lg:pt-10 lg:pb-6">
                <div class="grid gap-x-8 lg:grid-cols-4">
                    <div class="pb-4 lg:pb-0">
                        <p class="text-2xl font-extrabold tracking-tight text-white">{{ \Illuminate\Support\Str::lower(config('app.name')) }}</p>
                        <p class="mt-1 text-sm">{{ __('site.footer.tagline') }}</p>
                        @if ($socials)
                            <p class="mt-4 text-sm font-semibold text-white">{{ __('site.footer.col_follow') }}</p>
                            <ul class="mt-2 flex flex-wrap gap-2">
                                @foreach ($socials as $s)
                                    <li><a href="{{ $s['url'] }}" rel="noopener me" target="_blank" class="flex size-10 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-white" title="{{ $s['name'] }}">{{ icon($s['icon'], 'size-5') }}<span class="sr-only">{{ $s['name'] }}</span></a></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    @foreach ($columns as $key => [$title, $links])
                        <details open class="group border-t border-white/10 lg:border-0" data-footer-section>
                            <summary class="flex cursor-pointer list-none items-center justify-between py-3 font-semibold text-white lg:cursor-default lg:pt-0 [&::-webkit-details-marker]:hidden">
                                {{ $title }} {{ icon('chevron-down', 'size-5 transition group-open:rotate-180 lg:hidden') }}
                            </summary>
                            <ul class="space-y-2 pb-4 text-sm">
                                @foreach ($links as [$url, $label])
                                    <li><a href="{{ $url }}" class="{{ $linkClass }}">{{ $label }}</a></li>
                                @endforeach
                            </ul>
                        </details>
                    @endforeach

                    @if ($popularCats || $popularCities)
                        <details open class="group border-t border-white/10 lg:border-0" data-footer-section>
                            <summary class="flex cursor-pointer list-none items-center justify-between py-3 font-semibold text-white lg:cursor-default lg:pt-0 [&::-webkit-details-marker]:hidden">
                                {{ __('site.footer.col_popular') }} {{ icon('chevron-down', 'size-5 transition group-open:rotate-180 lg:hidden') }}
                            </summary>
                            <ul class="space-y-2 pb-4 text-sm">
                                @foreach ($popularCats as $c)
                                    <li><a href="{{ lroute('browse.category', ['category' => $c['slug']]) }}" class="{{ $linkClass }}">{{ translated($c['name']) }}</a></li>
                                @endforeach
                                @foreach ($popularCities as $c)
                                    <li><a href="{{ lroute('search', ['city' => $c['slug']]) }}" class="{{ $linkClass }}">{{ translated($c['name']) }}</a></li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </div>

                <div class="mt-4 flex flex-col gap-3 border-t border-white/10 pt-4 text-sm sm:flex-row sm:items-center sm:justify-between">
                    @if ($locales->showSwitcher())
                        <nav aria-label="{{ __('site.footer.language') }}" class="flex flex-wrap items-center gap-x-4 gap-y-1">
                            {{ icon('globe', 'size-4') }}
                            @foreach ($locales->enabled() as $code)
                                <a href="{{ $locales->urlFor($code) }}" hreflang="{{ $code }}" lang="{{ $code }}" data-lang-switch="{{ $code }}"
                                   @class([$linkClass, 'font-semibold text-white' => $code === app()->getLocale()])
                                   @if ($code === app()->getLocale()) aria-current="true" @endif>{{ $locales->supported()[$code]['native'] }}</a>
                            @endforeach
                        </nav>
                    @endif
                    <p>© {{ now()->year }} {{ \Illuminate\Support\Str::lower(config('app.name')) }}. {{ __('site.footer.rights') }}</p>
                </div>
            </div>
        </footer>
    </div>
@else
<footer class="mt-12 border-t border-stone-200 bg-white" data-site-footer>
    <div class="mx-auto max-w-6xl px-4 py-6 text-sm text-stone-600">
        <ul class="flex flex-wrap gap-x-5 gap-y-2">
            <li><a href="{{ lroute('page', ['slug' => 'about']) }}" class="hover:text-brand-700 hover:underline">{{ __('site.footer.about') }}</a></li>
            <li><a href="{{ lroute('page', ['slug' => 'safety']) }}" class="hover:text-brand-700 hover:underline">{{ __('site.footer.safety') }}</a></li>
            <li><a href="{{ lroute('page', ['slug' => 'terms']) }}" class="hover:text-brand-700 hover:underline">{{ __('site.footer.terms') }}</a></li>
            <li><a href="{{ lroute('page', ['slug' => 'privacy']) }}" class="hover:text-brand-700 hover:underline">{{ __('site.footer.privacy') }}</a></li>
            @feature('help.centre')
                <li><a href="{{ lroute('help') }}" class="hover:text-brand-700 hover:underline">{{ __('site.footer.help') }}</a></li>
            @endfeature
        </ul>
        <p class="mt-4">© {{ now()->year }} {{ \Illuminate\Support\Str::lower(config('app.name')) }}. {{ __('site.footer.rights') }}</p>
    </div>
</footer>
@endfeature
