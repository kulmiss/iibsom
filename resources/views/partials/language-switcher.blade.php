@php($locales = app(\App\Support\Localization\Locales::class))
@if ($locales->showSwitcher())
    <div class="flex items-center gap-1 text-sm" role="group" aria-label="{{ __('site.nav.language') }}">
        @foreach ($locales->enabled() as $code)
            <a href="{{ $locales->urlFor($code) }}"
               hreflang="{{ $code }}"
               lang="{{ $code }}"
               data-lang-switch="{{ $code }}"
               @class(['rounded px-2 py-1', 'bg-white font-semibold text-brand-800' => $code === app()->getLocale(), 'hover:bg-white/15' => $code !== app()->getLocale()])
               @if ($code === app()->getLocale()) aria-current="true" @endif>
                {{ $locales->supported()[$code]['native'] }}
            </a>
        @endforeach
    </div>
@endif
