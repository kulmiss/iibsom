{{--
  Language menu: a globe with the current language code (e.g. EN), and a list of the
  languages that are ON, each in its own name. <details> works with no JavaScript;
  language-switch.js adds instant switching, Escape and click-outside to close.
--}}
@php($locales = app(\App\Support\Localization\Locales::class))
@if ($locales->showSwitcher())
    <details class="relative" data-lang-menu>
        <summary class="flex h-9 cursor-pointer list-none items-center gap-1 rounded-lg px-2 text-sm font-semibold hover:bg-white/15 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white [&::-webkit-details-marker]:hidden">
            {{ icon('globe', 'size-5') }}
            {{-- The spoken name starts with the visible "EN" (screen readers and voice control match it). --}}
            <span>{{ strtoupper(app()->getLocale()) }}</span><span class="sr-only"> – {{ __('site.nav.language') }}: {{ $locales->supported()[app()->getLocale()]['native'] }}</span>
            {{ icon('chevron-down', 'size-4 opacity-80') }}
        </summary>
        <ul class="absolute end-0 z-30 mt-1 min-w-40 overflow-hidden rounded-xl bg-white py-1 text-stone-800 shadow-lg ring-1 ring-stone-200" aria-label="{{ __('site.nav.language') }}">
            @foreach ($locales->enabled() as $code)
                <li>
                    <a href="{{ $locales->urlFor($code) }}"
                       hreflang="{{ $code }}"
                       lang="{{ $code }}"
                       data-lang-switch="{{ $code }}"
                       @class(['flex items-center justify-between gap-3 px-4 py-2.5 hover:bg-stone-50 focus-visible:bg-stone-100 focus-visible:outline-none', 'font-semibold text-brand-800' => $code === app()->getLocale()])
                       @if ($code === app()->getLocale()) aria-current="true" @endif>
                        <span>{{ $locales->supported()[$code]['native'] }}</span>
                        @if ($code === app()->getLocale())
                            {{ icon('check', 'size-4') }}
                        @else
                            <span class="text-xs text-stone-400" aria-hidden="true">{{ strtoupper($code) }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </details>
@endif
