@extends('layouts.app')

@section('title', __('site.home.title'))

@php($catalog = app(\App\Support\Catalog::class))
@php($categories = $catalog->categories())
@php($cities = $catalog->cities())
@php($popular = $catalog->popularCategories(8))
@php($latest = \App\Search\DatabaseAdSearch::publicQuery()->with(['category:id,slug,icon,parent_id', 'city:id,name,slug', 'cover'])->latest('published_at')->limit(12)->get())
@php($banners = feature('site.banners') ? \App\Models\Banner::forHome() : [])

@section('content')
    {{-- Big search (inspired by jiji.com, in iibsom colours) --}}
    <section class="rounded-b-3xl bg-brand-700 text-white">
        <div class="mx-auto max-w-3xl px-4 pb-7 pt-6 text-center sm:pb-16 sm:pt-14">
            <h1 class="text-xl font-bold sm:text-3xl">{{ __('site.home.heading') }}</h1>

            <form class="mt-5 flex gap-2" role="search" action="{{ lroute('search') }}" method="get">
                {{-- Picker only when there is a choice (2+ live cities). --}}
                @if (count($cities) > 1)
                    <label for="city" class="sr-only">{{ __('site.home.location_label') }}</label>
                    <select id="city" name="city" class="w-32 shrink-0 truncate rounded-lg border-0 bg-white px-3 py-3 text-stone-800 sm:w-48 sm:px-4">
                        <option value="">{{ __('site.home.all_somalia') }}</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city['slug'] }}">{{ translated($city['name']) }}</option>
                        @endforeach
                    </select>
                @endif

                <div class="relative flex-1">
                    <label for="q" class="sr-only">{{ __('site.home.search_placeholder') }}</label>
                    <input id="q" name="q" type="search" placeholder="{{ __('site.home.search_placeholder') }}"
                           class="w-full rounded-lg border-0 bg-white py-3 pl-3 pr-11 sm:pl-4 text-stone-900 placeholder:text-stone-500 focus:ring-2 focus:ring-accent-400">
                    <button type="submit" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-stone-500 hover:text-brand-700">
                        {{ icon('search') }}
                        <span class="sr-only">{{ __('site.home.search_button') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </section>

    <div class="mx-auto mt-5 grid max-w-6xl gap-5 px-4 lg:grid-cols-[18rem_1fr]">
        {{-- Category list (desktop). Hover, keyboard focus or the arrow button opens a side panel with the sub categories.
             Phones use the tiles below: a tap opens the category page, which starts with its sub categories. --}}
        <aside class="relative hidden lg:block" aria-label="{{ __('site.home.categories') }}" data-cat-menu>
            <ul class="rounded-xl bg-white shadow-sm ring-1 ring-stone-200">
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
                            <div id="cat-panel-{{ $category['id'] }}" data-cat-panel
                                 class="invisible absolute start-full top-0 z-20 min-h-full w-[34rem] rounded-xl bg-white p-4 opacity-0 shadow-lg ring-1 ring-stone-200 transition group-hover/cat:visible group-hover/cat:opacity-100 data-[open]:visible data-[open]:opacity-100">
                                <p class="flex items-center justify-between gap-3 font-semibold">
                                    <span>{{ translated($category['name']) }}</span>
                                    <a href="{{ lroute('browse.category', ['category' => $category['slug']]) }}" class="text-sm font-medium text-brand-700 hover:underline">{{ __('ads.browse.all_in', ['category' => translated($category['name'])]) }} ({{ number_format($category['ads_count']) }})</a>
                                </p>
                                <ul class="mt-3 grid grid-cols-2 gap-1">
                                    @foreach ($subs as $sub)
                                        <li>
                                            <a href="{{ lroute('browse.category', ['category' => $sub['slug']]) }}" class="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-brand-50 focus-visible:bg-brand-50">
                                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-stone-50 text-brand-700">{{ icon($sub['icon'], 'size-5') }}</span>
                                                <span class="min-w-0">
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
        </aside>

        <div class="min-w-0">
            {{-- Quick links --}}
            <div class="flex flex-wrap gap-2">
                <a href="{{ lroute('post') }}" class="chip bg-accent-400/30 ring-accent-400">{{ icon('tag', 'size-4') }} {{ __('site.home.how_to_sell') }}</a>
                @feature('site.safety_box')
                    <a href="{{ lroute('page', ['slug' => 'safety']) }}" class="chip bg-brand-50 ring-brand-200">{{ icon('shield-check', 'size-4') }} {{ __('site.home.safety_tips') }}</a>
                @endfeature
            </div>

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
            </nav>

            {{-- Popular categories --}}
            @if ($popular)
                <h2 class="mt-6 text-lg font-semibold">{{ __('site.home.popular_categories') }}</h2>
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
            @feature('recommendations')
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
            @endfeature

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
                <div class="mt-3 rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center">
                    <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-brand-50 text-brand-700">{{ icon('trending-up', 'size-6') }}</span>
                    <p class="mt-3 font-semibold">{{ __('site.home.no_ads_title') }}</p>
                    <p class="mt-1 text-sm text-stone-600">{{ __('site.home.no_ads_text') }}</p>
                    @feature('ads.posting')
                        <a href="{{ lroute('post') }}" class="btn-sell mt-4">{{ __('site.nav.post_ad') }}</a>
                    @endfeature
                </div>
            @endif
        </div>
    </div>
@endsection
