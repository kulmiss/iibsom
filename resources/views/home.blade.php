@extends('layouts.app')

@section('title', __('site.home.title'))

@php($catalog = app(\App\Support\Catalog::class))
@php($categories = $catalog->categories())
@php($cities = $catalog->cities())
{{-- Each part below has its own switch: Admin → Home page (PR 86) lists them in this order (config/home.php). --}}
@php($popular = feature('home.popular_categories') ? $catalog->popularCategories(8) : [])
@php($latest = \App\Search\DatabaseAdSearch::publicQuery()->with(['category:id,slug,icon,parent_id', 'city:id,name,slug', 'cover'])->withPhotoCount()->withCardSeller()->latest('published_at')->limit(12)->get())
@php($popularNow = \App\Support\Ads\PopularNow::ads())
@php($banners = feature('site.banners') ? \App\Models\Banner::forHome() : [])
@php($popularSearches = \App\Support\Home\HomeSections::popularSearches())
@php($places = \App\Support\Home\HomeSections::places())
@php($locations = \App\Support\Home\HomeSections::locations())
@php($featured = \App\Support\Home\HomeSections::featured())
@php($trusted = \App\Support\Home\HomeSections::trustedSellers())
@php($canPost = feature('ads.posting') && (auth()->check() || feature('login.google')))

@section('content')
    @if (session('report_thanks'))
        {{-- After a report that hid the ad (PR 89): the reporter lands here; only signed-in people (never cached). --}}
        <p class="mx-auto mt-3 max-w-6xl px-4" role="status"><span class="block rounded-xl bg-green-50 px-4 py-3 text-sm text-green-800 ring-1 ring-green-200" data-report-thanks>{{ __('ads.page.report_thanks') }}</span></p>
    @endif
    {{-- Big search (inspired by jiji.com, in iibsom colours) --}}
    <section class="rounded-b-3xl bg-brand-700 text-white">
        <div class="mx-auto max-w-3xl px-4 pb-7 pt-6 text-center sm:pb-16 sm:pt-14">
            <h1 class="text-xl font-bold sm:text-3xl">{{ __('site.home.heading') }}</h1>
            @feature('home.promise')
                <p class="mt-1.5 text-sm text-white sm:text-base" data-home-promise>{{ __('site.home.promise') }}</p>
            @endfeature

            <form class="mt-5 flex gap-2" role="search" action="{{ lroute('search') }}" method="get" data-search-form>
                {{-- Place picker (PR 87): a button that opens cities → districts with counts. It needs JavaScript; without it
                     the simple city list below stays (the button and its fields are hidden and sent only with JavaScript). --}}
                @if ($locations)
                    <button type="button" class="hidden w-32 shrink-0 items-center gap-1.5 rounded-lg bg-white px-3 py-3 text-start text-stone-800 sm:w-48 sm:px-4 [.js_&]:flex" aria-haspopup="dialog" aria-controls="place-picker" data-loc-open>
                        {{ icon('map-pin', 'size-4 shrink-0 text-brand-700') }}<span class="min-w-0 flex-1 truncate" data-loc-label>{{ __('site.home.all_somalia') }}</span>{{ icon('chevron-down', 'size-4 shrink-0 text-stone-500') }}
                    </button>
                    <input type="hidden" name="city" value="" disabled data-loc-city>
                    <input type="hidden" name="area" value="" disabled data-loc-area>
                @endif
                {{-- Simple list only when there is a choice (2+ live cities). --}}
                @if (count($cities) > 1)
                    <label for="city" class="sr-only">{{ __('site.home.location_label') }}</label>
                    <select id="city" name="city" @class(['w-32 shrink-0 truncate rounded-lg border-0 bg-white px-3 py-3 text-stone-800 sm:w-48 sm:px-4', '[.js_&]:hidden' => (bool) $locations]) @if ($locations) data-loc-fallback @endif>
                        <option value="">{{ __('site.home.all_somalia') }}</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city['slug'] }}">{{ translated($city['name']) }}</option>
                        @endforeach
                    </select>
                @endif

                <div class="relative flex-1">
                    <label for="q" class="sr-only">{{ __('site.home.search_placeholder') }}</label>
                    <input id="q" name="q" type="search" autocomplete="off" placeholder="{{ __('site.home.search_placeholder') }}"
                           @feature('search.suggestions') data-suggest="{{ lroute('search.suggest') }}" @endfeature
                           class="w-full rounded-lg border-0 bg-white py-3 pl-3 pr-11 sm:pl-4 text-stone-900 placeholder:text-stone-500 focus:ring-2 focus:ring-accent-400">
                    <button type="submit" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-stone-500 hover:text-brand-700">
                        {{ icon('search') }}
                        <span class="sr-only">{{ __('site.home.search_button') }}</span>
                    </button>
                </div>
            </form>

            @if ($popularSearches)
                {{-- Words from live ad titles only (made at night), never what visitors typed. --}}
                <nav class="-mx-4 mt-3 flex items-center gap-2 overflow-x-auto px-4 pb-1 text-sm sm:mx-0 sm:flex-wrap sm:justify-center sm:overflow-visible sm:px-0" aria-label="{{ __('site.home.popular_searches') }}" data-popular-searches>
                    <span class="shrink-0 text-white">{{ __('site.home.popular_searches') }}</span>
                    @foreach ($popularSearches as $term)
                        <a href="{{ lroute('search', ['q' => $term]) }}" class="shrink-0 rounded-full bg-brand-800 px-3 py-1 font-medium text-white ring-1 ring-white/30 hover:bg-brand-900">{{ $term }}</a>
                    @endforeach
                </nav>
            @endif
            @if ($canPost && feature('home.post_button'))
                <a href="{{ lroute('post') }}" class="btn-sell mt-4 px-5 py-2.5" data-home-post>{{ icon('plus', 'size-4') }} {{ __('site.home.post_free') }}</a>
            @endif
        </div>
    </section>

    @if ($locations)
        @include('partials.place-picker', ['locations' => $locations])
    @endif

    <div class="mx-auto mt-5 grid max-w-6xl gap-5 px-4 lg:grid-cols-[18rem_1fr]">
        {{-- Category list (desktop). Hover, keyboard focus or the arrow button opens a side panel with the sub categories.
             Phones use the tiles below: a tap opens the category page, which starts with its sub categories. --}}
        {{-- Sticky (PR 87, "home.sticky_categories"): the list scrolls inside on short screens; the side panels are placed
             against the aside (not the list), so the list's scroll never cuts them off. A sticky box makes its own layer,
             so it needs z-20: without it the ad cards (photo labels, Save buttons) were drawn over the panel (PR 91).
             No visible scrollbar (PR 92, owner): on Windows its strip sat between the list and the side panel, so moving
             the mouse to a sub category closed the panel. The list still scrolls with the wheel or a finger. --}}
        @php($stickyCats = feature('home.sticky_categories'))
        <aside @class(['hidden', 'relative lg:block' => ! $stickyCats, 'lg:sticky lg:top-20 lg:z-20 lg:flex lg:max-h-[calc(100dvh-6rem)] lg:flex-col lg:self-start' => $stickyCats]) aria-label="{{ __('site.home.categories') }}" data-cat-menu @if ($stickyCats) data-sticky @endif>
            <ul @class(['rounded-xl bg-white shadow-sm ring-1 ring-stone-200', 'min-h-0 flex-1 overflow-y-auto overscroll-contain [scrollbar-width:none] [&::-webkit-scrollbar]:hidden' => $stickyCats])>
                @foreach ($categories as $category)
                    @php($subs = $catalog->visibleChildren($category))
                    <li class="group/cat border-b border-stone-100 last:border-0" data-cat-item>
                        <div class="flex items-center hover:bg-brand-50">
                            <a href="{{ lroute('browse.category', ['category' => $category['slug']]) }}" class="flex min-w-0 flex-1 items-center gap-3 py-2.5 ps-3">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700">{{ icon($category['icon']) }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-medium leading-tight">{{ translated($category['name']) }}</span>
                                    <span class="block text-xs text-stone-500">{{ trans_choice('site.ads_count', $category['ads_count'], ['count' => number_format($category['ads_count'])]) }}</span>
                                </span>
                            </a>
                            @if ($subs)
                                <button type="button" class="flex size-10 items-center justify-center text-stone-400 hover:text-brand-700" aria-expanded="false" aria-controls="cat-panel-{{ $category['id'] }}" data-cat-toggle
                                        aria-label="{{ __('ads.browse.sub_categories', ['category' => translated($category['name'])]) }}">{{ icon('chevron-right', 'size-4') }}</button>
                            @endif
                        </div>
                        @if ($subs)
                            {{-- Narrow panel, as wide as the list (owner, 2026-11-20, like Jiji): name with the count under it. --}}
                            {{-- PR 93: long lists (Services has 26) scroll inside the panel, which never goes past the screen. --}}
                            <div id="cat-panel-{{ $category['id'] }}" data-cat-panel
                                 @class(['invisible absolute start-full top-0 z-20 min-h-full w-72 rounded-xl bg-white p-2 opacity-0 shadow-lg ring-1 ring-stone-200 transition group-hover/cat:visible group-hover/cat:opacity-100 data-[open]:visible data-[open]:opacity-100',
                                         'max-h-[calc(100dvh-6rem)] overflow-y-auto overscroll-contain' => $stickyCats])>
                                <a href="{{ lroute('browse.category', ['category' => $category['slug']]) }}" class="block rounded-lg px-2 py-2 hover:bg-brand-50 focus-visible:bg-brand-50">
                                    <span class="block font-semibold leading-tight">{{ translated($category['name']) }}</span>
                                    <span class="block text-xs font-medium text-brand-700">{{ __('ads.browse.all_in', ['category' => translated($category['name'])]) }} ({{ number_format($category['ads_count']) }})</span>
                                </a>
                                {{-- One sub category per line (owner's wish, 2026-11-18). --}}
                                <ul class="mt-1 grid gap-0.5 border-t border-stone-100 pt-1" data-cat-subs>
                                    @foreach ($subs as $sub)
                                        <li>
                                            <a href="{{ lroute('browse.category', ['category' => $sub['slug']]) }}" class="flex items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-brand-50 focus-visible:bg-brand-50">
                                                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-stone-50 text-brand-700">{{ icon($sub['icon'], 'size-5') }}</span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="block text-sm font-medium leading-tight">{{ translated($sub['name']) }}</span>
                                                    <span class="block text-xs text-stone-500">{{ trans_choice('site.ads_count', $sub['ads_count'], ['count' => number_format($sub['ads_count'])]) }}</span>
                                                </span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
            @feature('catalog.finder')
                <a href="{{ lroute('categories') }}" class="mt-2 flex shrink-0 items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-brand-800 hover:bg-brand-50" data-finder-link>{{ icon('layout-grid', 'size-4') }} {{ __('ads.finder.title') }}</a>
            @endfeature
        </aside>

        <div class="min-w-0">
            {{-- Quick links --}}
            @feature('home.quick_links')
                <div class="flex flex-wrap gap-2" data-home-quick-links>
                    <a href="{{ feature('help.how_to_sell') && feature('help.centre') ? lroute('help.show', ['article' => 'how-to-sell']) : lroute('post') }}" class="chip bg-accent-400/30 ring-accent-400">{{ icon('tag', 'size-4') }} {{ __('site.home.how_to_sell') }}</a>
                    @feature('site.safety_box')
                        <a href="{{ lroute('page', ['slug' => 'safety']) }}" class="chip bg-brand-50 ring-brand-200">{{ icon('shield-check', 'size-4') }} {{ __('site.home.safety_tips') }}</a>
                    @endfeature
                </div>
            @endfeature

            {{-- Home banners (admin → Home banners) --}}
            @if ($banners)
                <div class="mt-4 flex snap-x snap-mandatory gap-3 overflow-x-auto pb-1" aria-label="Banners">
                    @foreach ($banners as $banner)
                        @php($img = '<img src="'.e($banner['small']).'" srcset="'.e($banner['small']).' 800w, '.e($banner['wide']).' 1600w" sizes="(min-width: 1024px) 860px, 100vw" alt="'.e(translated($banner['title'])).'" class="h-36 w-full rounded-xl object-cover sm:h-44" '.($loop->first ? 'fetchpriority="high"' : 'loading="lazy"').'>')
                        <div class="w-full shrink-0 snap-start">
                            @if ($banner['link_url'])
                                <a href="{{ $banner['link_url'] }}" @if (str_starts_with($banner['link_url'], 'https://')) rel="noopener" @endif>{!! $img !!}</a>
                            @else
                                {!! $img !!}
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @include('partials.banner-ad', ['bannerAd' => \App\Models\Banner::pick('home'), 'class' => 'mt-4'])

            {{-- Category tiles (phones), like a mobile app --}}
            <nav class="mt-4 grid grid-cols-4 gap-x-2 gap-y-4 sm:grid-cols-6 lg:hidden" aria-label="{{ __('site.home.categories') }}">
                @feature('ads.posting')
                    <a href="{{ lroute('post') }}" class="tile">
                        <span class="tile-icon bg-accent-400 text-white">
                            <span class="flex size-9 items-center justify-center rounded-full bg-white/90 text-accent-500">{{ icon('plus', 'size-6') }}</span>
                        </span>
                        <span>{{ __('site.home.post_ad_tile') }}</span>
                    </a>
                @endfeature
                <a href="#trending" class="tile">
                    <span class="tile-icon bg-orange-50 text-orange-500">{{ icon('trending-up', 'size-8') }}</span>
                    <span>{{ __('site.home.trending') }}</span>
                </a>
                @foreach ($categories as $category)
                    <a href="{{ lroute('browse.category', ['category' => $category['slug']]) }}" class="tile">
                        <span class="tile-icon bg-brand-50 text-brand-700">{{ icon($category['icon'], 'size-8') }}</span>
                        <span>{{ translated($category['name']) }}</span>
                    </a>
                @endforeach
                @feature('catalog.finder')
                    <a href="{{ lroute('categories') }}" class="tile" data-finder-tile>
                        <span class="tile-icon bg-stone-100 text-stone-700">{{ icon('layout-grid', 'size-8') }}</span>
                        <span>{{ __('ads.finder.tile') }}</span>
                    </a>
                @endfeature
            </nav>

            {{-- Popular categories --}}
            @if ($popular)
                <h2 class="mt-6 text-lg font-semibold" data-home-popular-categories>{{ __('site.home.popular_categories') }}</h2>
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach ($popular as $category)
                        <a href="{{ lroute('browse.category', ['category' => $category['slug']]) }}" class="group flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm ring-1 ring-stone-200 hover:ring-brand-200">
                            <span class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700 group-hover:bg-brand-700 group-hover:text-white">{{ icon($category['icon'], 'size-6') }}</span>
                            <span class="text-sm font-medium leading-tight">{{ translated($category['name']) }}</span>
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Recommended for you + recently viewed: filled by for-you.js from this browser's own history --}}
            @if (feature('recommendations') && feature('home.recommended'))
                <div data-for-you data-url="{{ lroute('for-you') }}" hidden class="mt-8">
                    <section data-slot="recommended" hidden>
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-lg font-semibold">{{ __('site.home.for_you') }}</h2>
                            <button type="button" data-clear-history class="text-xs font-medium text-stone-500 underline">{{ __('site.home.clear_history') }}</button>
                        </div>
                        <div data-cards class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4"></div>
                    </section>
                    <section data-slot="recent" hidden class="mt-6">
                        <h2 class="text-lg font-semibold">{{ __('site.home.recently_viewed') }}</h2>
                        <div data-cards class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4"></div>
                    </section>
                    <p class="mt-2 text-xs text-stone-500">{{ __('site.home.history_note') }}</p>
                </div>
            @endif

            {{-- Featured (boosted) ads: needs paid boosts; hidden when none --}}
            @if ($featured->isNotEmpty())
                <section class="mt-8" aria-labelledby="featured" data-home-featured>
                    <h2 id="featured" class="flex items-center gap-2 text-lg font-semibold">{{ icon('rocket', 'size-5 text-brand-700') }} {{ __('site.home.featured') }}</h2>
                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                        @foreach ($featured as $ad)
                            @include('ads._card', ['ad' => $ad])
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Popular now: most viewed ads of the last 30 days (needs Ad statistics) --}}
            @if ($popularNow->isNotEmpty())
                <section class="mt-8" aria-labelledby="popular-now">
                    <h2 id="popular-now" class="flex items-center gap-2 text-lg font-semibold">{{ icon('flame', 'size-5 text-orange-600') }} {{ __('site.home.popular_now') }}</h2>
                    <p class="text-sm text-stone-600">{{ __('site.home.popular_now_note') }}</p>
                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                        @foreach ($popularNow as $ad)
                            @include('ads._card', ['ad' => $ad])
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Latest ads --}}
            <h2 id="trending" class="mt-8 scroll-mt-20 text-lg font-semibold">{{ __('site.home.trending_ads') }}</h2>
            @if ($latest->isNotEmpty())
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    @foreach ($latest as $ad)
                        @include('ads._card', ['ad' => $ad])
                    @endforeach
                </div>
                <a href="{{ lroute('search') }}" class="mt-4 inline-block font-medium text-brand-700 underline">{{ __('ads.browse.all_ads') }} →</a>
            @else
                <x-empty-state class="mt-3" icon="trending-up" :title="__('site.home.no_ads_title')" :text="__('site.home.no_ads_text')">
                    @feature('ads.posting')
                        <a href="{{ lroute('post') }}" class="btn-sell">{{ __('site.nav.post_ad') }}</a>
                    @endfeature
                </x-empty-state>
            @endif

            @include('partials.home-more')
        </div>
    </div>
@endsection
