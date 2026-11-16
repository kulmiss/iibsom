@extends('layouts.app')

@section('title', __('site.home.title'))

@php($catalog = app(\App\Support\Catalog::class))
@php($categories = $catalog->categories())
@php($cities = $catalog->cities())
@php($popular = $catalog->popularCategories(8))

@section('content')
    {{-- Big search (inspired by jiji.com, in Iibsom colours) --}}
    <section class="rounded-b-3xl bg-brand-700 text-white">
        <div class="mx-auto max-w-3xl px-4 pb-10 pt-8 text-center sm:pb-16 sm:pt-14">
            <h1 class="text-2xl font-bold sm:text-3xl">{{ __('site.home.heading') }}</h1>

            {{-- Search results page comes in the search PR. --}}
            <form class="mt-5 flex flex-col gap-2 sm:flex-row" role="search" action="#" method="get">
                <label for="city" class="sr-only">{{ __('site.home.location_label') }}</label>
                <select id="city" name="city" class="rounded-lg border-0 bg-white px-4 py-3 text-stone-800 sm:w-48">
                    @if (count($cities) > 1)
                        <option value="">{{ __('site.home.all_somalia') }}</option>
                    @endif
                    @foreach ($cities as $city)
                        <option value="{{ $city['slug'] }}">{{ translated($city['name']) }}</option>
                    @endforeach
                </select>

                <div class="relative flex-1">
                    <label for="q" class="sr-only">{{ __('site.home.search_placeholder') }}</label>
                    <input id="q" name="q" type="search" placeholder="{{ __('site.home.search_placeholder') }}"
                           class="w-full rounded-lg border-0 bg-white py-3 pl-4 pr-12 text-stone-900 placeholder:text-stone-500 focus:ring-2 focus:ring-accent-400">
                    <button type="submit" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-stone-500 hover:text-brand-700">
                        {{ icon('search') }}
                        <span class="sr-only">{{ __('site.home.search_button') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </section>

    <div class="mx-auto mt-5 grid max-w-6xl gap-5 px-4 lg:grid-cols-[18rem_1fr]">
        {{-- Category list (desktop) --}}
        <aside class="hidden lg:block" aria-label="{{ __('site.home.categories') }}">
            <ul class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-stone-200">
                @foreach ($categories as $category)
                    <li class="border-b border-stone-100 last:border-0">
                        <a href="#" class="flex items-center gap-3 px-3 py-2.5 hover:bg-brand-50">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700">{{ icon($category['icon']) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium leading-tight">{{ translated($category['name']) }}</span>
                                <span class="block text-xs text-stone-500">{{ trans_choice('site.ads_count', $category['ads_count'], ['count' => number_format($category['ads_count'])]) }}</span>
                            </span>
                            {{ icon('chevron-right', 'size-4 text-stone-400') }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </aside>

        <div class="min-w-0">
            {{-- Category grid (phones) --}}
            <nav class="grid grid-cols-3 gap-2 sm:grid-cols-5 lg:hidden" aria-label="{{ __('site.home.categories') }}">
                @foreach ($categories as $category)
                    <a href="#" class="flex flex-col items-center gap-1.5 rounded-xl bg-white p-3 text-center shadow-sm ring-1 ring-stone-200">
                        <span class="flex size-11 items-center justify-center rounded-full bg-brand-50 text-brand-700">{{ icon($category['icon'], 'size-6') }}</span>
                        <span class="text-xs font-medium leading-tight">{{ translated($category['name']) }}</span>
                    </a>
                @endforeach
            </nav>

            {{-- Quick links --}}
            <div class="mt-5 flex flex-wrap gap-2 lg:mt-0">
                <a href="#" class="chip bg-accent-400/30 ring-accent-400">{{ icon('tag', 'size-4') }} {{ __('site.home.how_to_sell') }}</a>
                @feature('site.safety_box')
                    <a href="#" class="chip bg-brand-50 ring-brand-200">{{ icon('shield-check', 'size-4') }} {{ __('site.home.safety_tips') }}</a>
                @endfeature
            </div>

            {{-- Popular categories --}}
            @if ($popular)
                <h2 class="mt-6 text-lg font-semibold">{{ __('site.home.popular_categories') }}</h2>
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach ($popular as $category)
                        <a href="#" class="group flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm ring-1 ring-stone-200 hover:ring-brand-200">
                            <span class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700 group-hover:bg-brand-700 group-hover:text-white">{{ icon($category['icon'], 'size-6') }}</span>
                            <span class="text-sm font-medium leading-tight">{{ translated($category['name']) }}</span>
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Trending ads (filled in the ads PRs) --}}
            <h2 class="mt-8 text-lg font-semibold">{{ __('site.home.trending_ads') }}</h2>
            <div class="mt-3 rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center">
                <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-brand-50 text-brand-700">{{ icon('trending-up', 'size-6') }}</span>
                <p class="mt-3 font-semibold">{{ __('site.home.no_ads_title') }}</p>
                <p class="mt-1 text-sm text-stone-600">{{ __('site.home.no_ads_text') }}</p>
                @feature('ads.posting')
                    <a href="#" class="btn-sell mt-4">{{ __('site.nav.post_ad') }}</a>
                @endfeature
            </div>
        </div>
    </div>
@endsection
