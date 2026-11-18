{{-- Lower part of the home page (PR 75). Each part has its own switch and real data only; empty parts are hidden.
     Same HTML for everyone (the guest home page is cached). --}}
@php($helpOn = feature('help.centre'))

{{-- Popular places --}}
@if (count($places) >= 2)
    <section class="mt-8" aria-labelledby="places" data-home-places>
        <h2 id="places" class="flex items-center gap-2 text-lg font-semibold">{{ icon('map-pin', 'size-5 text-brand-700') }} {{ __('site.home.places_title') }}</h2>
        <ul class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
            @foreach ($places as $place)
                <li>
                    <a href="{{ $place['url'] }}" class="flex items-center justify-between gap-2 rounded-xl bg-white px-3 py-2.5 shadow-sm ring-1 ring-stone-200 hover:ring-brand-300">
                        <span class="min-w-0 truncate font-medium">{{ $place['label'] }}</span>
                        <span class="shrink-0 text-xs text-stone-500">{{ trans_choice('site.ads_count', $place['count'], ['count' => number_format($place['count'])]) }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif

{{-- Shops and verified sellers (needs shops or the verified badge) --}}
@if ($trusted)
    <section class="mt-8" aria-labelledby="trusted" data-home-trusted>
        <h2 id="trusted" class="flex items-center gap-2 text-lg font-semibold">{{ icon('badge-check', 'size-5 text-brand-700') }} {{ __('site.home.trusted_title') }}</h2>
        <ul class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
            @foreach ($trusted as $seller)
                <li>
                    <a href="{{ $seller['url'] }}" class="flex h-full items-center gap-3 rounded-xl bg-white p-3 shadow-sm ring-1 ring-stone-200 hover:ring-brand-300">
                        @if ($seller['logo'])
                            <img src="{{ $seller['logo'] }}" alt="" width="44" height="44" loading="lazy" class="size-11 shrink-0 rounded-full object-cover">
                        @else
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-700 font-bold text-white" aria-hidden="true">{{ $seller['initial'] }}</span>
                        @endif
                        <span class="min-w-0">
                            <span class="block truncate font-medium">{{ $seller['name'] }}</span>
                            <span class="flex items-center gap-1 text-xs text-stone-500">{{ icon($seller['shop'] ? 'store' : 'badge-check', 'size-3.5 text-brand-700') }} {{ $seller['shop'] ? __('site.home.shop') : __('site.home.verified') }} · {{ trans_choice('site.ads_count', $seller['ads'], ['count' => number_format($seller['ads'])]) }}</span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif

{{-- How it works: buy and sell in 3 steps --}}
@feature('home.how_it_works')
    <section class="mt-10" aria-labelledby="how" data-home-how>
        <h2 id="how" class="text-lg font-semibold">{{ __('site.home.how_title') }}</h2>
        <div class="mt-3 grid gap-3 md:grid-cols-2">
            {{-- Full text keys (not built from parts) so the Somali review sheet finds each one. --}}
            @php($howSides = [
                'buy' => ['title' => __('site.home.how_buy'), 'steps' => [
                    ['search', __('site.home.buy_1'), __('site.home.buy_1_text')],
                    ['message-circle', __('site.home.buy_2'), __('site.home.buy_2_text')],
                    ['shield-check', __('site.home.buy_3'), __('site.home.buy_3_text')],
                ]],
                'sell' => ['title' => __('site.home.how_sell'), 'steps' => [
                    ['camera', __('site.home.sell_1'), __('site.home.sell_1_text')],
                    ['square-plus', __('site.home.sell_2'), __('site.home.sell_2_text')],
                    ['phone-call', __('site.home.sell_3'), __('site.home.sell_3_text')],
                ]],
            ])
            @foreach ($howSides as $side => $how)
                <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-stone-200">
                    <h3 class="font-semibold text-brand-800">{{ $how['title'] }}</h3>
                    <ol class="mt-3 space-y-3">
                        @foreach ($how['steps'] as $i => [$stepIcon, $stepTitle, $stepText])
                            <li class="flex gap-3">
                                <span class="relative flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                                    {{ icon($stepIcon, 'size-5') }}
                                    <span class="absolute -end-1 -top-1 flex size-5 items-center justify-center rounded-full bg-accent-400 text-[11px] font-bold text-stone-900">{{ $i + 1 }}</span>
                                </span>
                                <span>
                                    <span class="block font-medium">{{ $stepTitle }}</span>
                                    <span class="block text-sm text-stone-600">{{ $stepText }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ol>
                    @if ($side === 'buy' && $helpOn && feature('help.how_to_buy'))
                        <a href="{{ lroute('help.show', ['article' => 'how-to-buy']) }}" class="mt-3 inline-block text-sm font-medium text-brand-700 underline">{{ __('site.home.how_more_buy') }}</a>
                    @elseif ($side === 'sell' && $helpOn && feature('help.how_to_sell'))
                        <a href="{{ lroute('help.show', ['article' => 'how-to-sell']) }}" class="mt-3 inline-block text-sm font-medium text-brand-700 underline">{{ __('site.home.how_to_sell') }}</a>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endfeature

{{-- Why people trust iibsom: true facts only --}}
@feature('home.trust_strip')
    @php($facts = \App\Support\Home\HomeSections::facts())
    <section class="mt-8 rounded-2xl bg-brand-50 p-4 sm:p-5" aria-labelledby="trust" data-home-trust>
        <h2 id="trust" class="text-lg font-semibold">{{ __('site.home.trust_title') }}</h2>
        <ul class="mt-3 grid gap-x-4 gap-y-2.5 text-sm sm:grid-cols-2">
            @if ($facts['ads'] > 0)
                <li class="flex items-center gap-2">{{ icon('layout-grid', 'size-5 shrink-0 text-brand-700') }} {{ trans_choice('site.home.trust_ads', $facts['ads'], ['count' => number_format($facts['ads'])]) }}</li>
            @endif
            @if ($facts['sellers'] > 0)
                <li class="flex items-center gap-2">{{ icon('user-check', 'size-5 shrink-0 text-brand-700') }} {{ trans_choice('site.home.trust_sellers', $facts['sellers'], ['count' => number_format($facts['sellers'])]) }}</li>
            @endif
            @if ($facts['checked'])
                <li class="flex items-center gap-2" data-trust-checked>{{ icon('circle-check', 'size-5 shrink-0 text-brand-700') }} {{ __('site.home.trust_checked') }}</li>
            @endif
            <li class="flex items-center gap-2">{{ icon('tag', 'size-5 shrink-0 text-brand-700') }} {{ __('site.home.trust_free') }}</li>
            @if (feature('ads.reports') || feature('ads.guest_reports'))
                <li class="flex items-center gap-2">{{ icon('shield-check', 'size-5 shrink-0 text-brand-700') }} {{ __('site.home.trust_report') }}</li>
            @endif
            <li class="flex items-center gap-2">{{ icon('phone', 'size-5 shrink-0 text-brand-700') }} {{ __('site.home.trust_number') }}</li>
        </ul>
        <p class="mt-4 flex gap-2 rounded-xl bg-white p-3 text-sm text-stone-700 ring-1 ring-brand-100">
            {{ icon('shield-check', 'size-5 shrink-0 text-emerald-600') }}
            <span>{{ __('site.home.safety_promise') }}
                @feature('site.safety_box')
                    <a href="{{ lroute('page', ['slug' => 'safety']) }}" class="font-medium text-brand-700 underline">{{ __('site.home.safety_tips') }}</a>
                @endfeature
            </span>
        </p>
    </section>
@endfeature

{{-- "Add iibsom to your phone": shown by pwa.js only when the phone offers it (no layout jump: it floats). --}}
@if (feature('pwa') && feature('pwa.install_prompt'))
    <div class="fixed inset-x-3 bottom-20 z-30 mx-auto flex max-w-md flex-wrap items-center gap-3 rounded-2xl bg-white p-3 shadow-lg ring-1 ring-stone-200 lg:bottom-6" role="dialog" aria-labelledby="install-title" data-install-card hidden>
        <img src="/icons/icon.svg" alt="" width="40" height="40" class="size-10 shrink-0 rounded-xl">
        <div class="min-w-0 flex-1">
            <p id="install-title" class="text-sm font-semibold">{{ __('site.home.install_title') }}</p>
            <p class="text-xs text-stone-600">{{ __('site.home.install_text') }}</p>
        </div>
        <div class="flex w-full justify-end gap-2 sm:ms-auto sm:w-auto">
            <button type="button" class="btn-chip text-sm" data-install-no>{{ __('site.home.install_no') }}</button>
            <button type="button" class="btn-primary" data-install-yes>{{ __('site.home.install_yes') }}</button>
        </div>
    </div>
@endif
