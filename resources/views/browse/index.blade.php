@extends('layouts.app')

@php
    $what = $category ? ($brand ? __('ads.browse.brand_title', ['brand' => $brand['label'], 'category' => $category->label]) : $category->label) : null;
    $title = $what ? $what.($city ? ' '.__('ads.browse.in_city', ['city' => $city->label]) : '') : (!empty($filters['q']) ? __('ads.browse.search_for', ['q' => $filters['q']]) : __('ads.browse.all_ads'));
    $action = $brand ? lroute('browse.brand', ['category' => $category->slug, 'city' => $city->slug, 'brand' => $brand['value']])
        : ($category ? lroute('browse.category', array_filter(['category' => $category->slug, 'city' => $city?->slug])) : lroute('search'));
    // Breadcrumbs: Home › Vehicles › Cars › Mogadishu › Toyota (also given to Google as BreadcrumbList).
    $crumbs = [[\Illuminate\Support\Str::lower(config('app.name')), lroute('home')]]; // "iibsom / Vehicles / Parts"
    if ($category?->parent) { $crumbs[] = [$category->parent->label, lroute('browse.category', ['category' => $category->parent->slug])]; }
    if ($category) { $crumbs[] = [$category->label, lroute('browse.category', ['category' => $category->slug])]; }
    if ($category && $city) { $crumbs[] = [$city->label, lroute('browse.category', ['category' => $category->slug, 'city' => $city->slug])]; }
    if ($brand) { $crumbs[] = [$brand['label'], $action]; }
@endphp

@section('title', $title)
@section('description', trans_choice('ads.browse.results', $results->total(), ['count' => number_format($results->total())]).' · '.$title.' · iibsom')

@push('head')
    {{-- Not for Google: later pages, searches, filters (a brand page's own brand is not a filter), and empty pages. --}}
    @php
        $extraFilters = array_diff_key($filters['f'] ?? [], $brand ? ['brand' => 1] : []);
    @endphp
    @if ($results->currentPage() > 1 || !empty($filters['q']) || $extraFilters || !empty($filters['min']) || !empty($filters['max']) || !empty($filters['condition'])
        || request()->hasAny(['area', 'posted', 'photos', 'verified', 'sort']) || $results->total() === 0)
        <meta name="robots" content="noindex, follow">
    @endif
    @if (count($crumbs) > 1)
        @php
            $breadcrumbLd = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => []];
            foreach ($crumbs as $i => [$name, $url]) {
                $breadcrumbLd['itemListElement'][] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $url];
            }
        @endphp
        <script type="application/ld+json" nonce="{{ Vite::cspNonce() }}">{!! json_encode($breadcrumbLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endif
@endpush

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-5" @if (feature('recommendations') && $category) data-track-cat="{{ $category->id }}" @endif>
        <nav class="text-sm text-stone-500" aria-label="Breadcrumb">
            <ol class="flex flex-wrap items-center gap-x-1">
                @foreach ($crumbs as $k => [$name, $url])
                    <li class="flex items-center gap-x-1">
                        @if ($k > 0)<span aria-hidden="true">›</span>@endif
                        @if ($loop->last)<span aria-current="page">{{ $name }}</span>@else<a href="{{ $url }}" class="hover:underline">{{ $name }}</a>@endif
                    </li>
                @endforeach
            </ol>
        </nav>
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <h1 class="mt-1 text-2xl font-bold">{{ $title }}</h1>
                <p class="text-sm text-stone-600" data-results-count>
                    @if ($category || !empty($filters['q']))
                        {{ trans_choice('ads.browse.results_for', $results->total(), ['count' => number_format($results->total()), 'what' => $what ?? '“'.$filters['q'].'”', 'where' => $city?->label ?? __('ads.browse.somalia')]) }}
                    @else
                        {{ trans_choice('ads.browse.results', $results->total(), ['count' => number_format($results->total())]) }}
                    @endif
                </p>
            </div>
            @feature('saved_searches')
                @include('browse._save-search')
            @endfeature
        </div>

        {{-- Sub categories: tiles on a main category (on phones this is the "sub category screen"), chips on a sub category. --}}
        @if ($category && ! $brand)
            @php($catalogRows = app(\App\Support\Catalog::class))
            @php($mainRow = collect($catalogRows->categories())->firstWhere('id', $category->parent_id ?? $category->id))
            @if ($mainRow)
                @php($subRows = $catalogRows->visibleChildren($mainRow))
                @if ($category->parent_id === null && $subRows)
                    <nav class="mt-4" aria-label="{{ __('ads.browse.sub_categories', ['category' => $category->label]) }}">
                        <ul class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                            @foreach ($subRows as $sub)
                                <li>
                                    <a href="{{ lroute('browse.category', array_filter(['category' => $sub['slug'], 'city' => $city?->slug])) }}" class="flex h-full flex-col items-center gap-1.5 rounded-xl bg-white p-3 text-center shadow-sm ring-1 ring-stone-200 hover:ring-brand-300">
                                        <span class="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-700">{{ icon($sub['icon'], 'size-6') }}</span>
                                        <span class="text-xs font-medium leading-tight sm:text-sm">{{ translated($sub['name']) }}</span>
                                        <span class="text-[11px] text-stone-500">{{ number_format($sub['ads_count']) }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                @elseif ($category->parent_id !== null && count($subRows) > 1)
                    <nav class="-mx-4 mt-3 overflow-x-auto px-4 pb-1" aria-label="{{ __('ads.browse.more_in', ['category' => translated($mainRow['name'])]) }}">
                        <ul class="flex gap-2">
                            <li class="shrink-0"><a href="{{ lroute('browse.category', array_filter(['category' => $mainRow['slug'], 'city' => $city?->slug])) }}" class="btn-chip">{{ __('ads.browse.all_in', ['category' => translated($mainRow['name'])]) }}</a></li>
                            @foreach ($subRows as $sub)
                                <li class="shrink-0">
                                    <a href="{{ lroute('browse.category', array_filter(['category' => $sub['slug'], 'city' => $city?->slug])) }}"
                                       @class(['btn-chip', '!bg-brand-700 !text-white !ring-brand-700' => $sub['id'] === $category->id])
                                       @if ($sub['id'] === $category->id) aria-current="page" @endif>
                                        {{ icon($sub['icon'], 'size-4') }} {{ translated($sub['name']) }} <span @class(['text-stone-500' => $sub['id'] !== $category->id])>{{ number_format($sub['ads_count']) }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                @endif
            @endif
        @endif

        @include('partials.banner-ad', ['bannerAd' => \App\Models\Banner::pick('list', $category, $city?->id), 'class' => 'mt-3'])

        {{-- Links Google (and people) can follow: popular brands here, the same category in other cities. --}}
        @if ($brandLinks)
            <nav class="-mx-4 mt-3 overflow-x-auto px-4" aria-label="{{ __('ads.browse.popular_brands') }}">
                <ul class="flex gap-2">
                    @foreach ($brandLinks as $b)
                        <li class="shrink-0"><a href="{{ lroute('browse.brand', ['category' => $category->slug, 'city' => $city->slug, 'brand' => $b['value']]) }}" class="btn-chip">{{ $b['label'] }} <span class="text-stone-500">{{ $b['count'] }}</span></a></li>
                    @endforeach
                </ul>
            </nav>
        @endif
        @if ($cityLinks)
            <p class="mt-2 text-sm text-stone-600">{{ __('ads.browse.other_cities') }}
                @foreach ($cityLinks as $c)
                    <a href="{{ $brand ? lroute('browse.brand', ['category' => $category->slug, 'city' => $c['slug'], 'brand' => $brand['value']]) : lroute('browse.category', ['category' => $category->slug, 'city' => $c['slug']]) }}" class="font-medium text-brand-800 underline">{{ translated($c['name']) }}</a>@if (! $loop->last), @endif
                @endforeach
            </p>
        @endif

        <div class="mt-4 grid gap-5 lg:grid-cols-[17rem_1fr]">
            @include('browse._filters')

            <div class="min-w-0 scroll-mt-20" id="results">
                {{-- Toolbar: Filters (phones), the filters in use, grid or list --}}
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <a href="#filters" class="btn-chip lg:hidden">{{ icon('sliders-horizontal', 'size-4') }} {{ __('ads.browse.filters') }}@if ($active) <span class="rounded-full bg-brand-700 px-1.5 text-xs text-white">{{ count($active) }}</span>@endif</a>
                    @if ($active)
                        <ul class="flex flex-wrap gap-2" aria-label="{{ __('ads.browse.active_filters') }}">
                            @foreach ($active as $chip)
                                <li><a href="{{ $chip['url'] }}" class="btn-chip !bg-brand-50 !ring-brand-200" aria-label="{{ __('ads.browse.remove_filter', ['filter' => $chip['label']]) }}">{{ $chip['label'] }} {{ icon('x', 'size-3.5') }}</a></li>
                            @endforeach
                        </ul>
                        <a href="{{ $action }}" class="text-sm font-medium text-brand-700 underline">{{ __('ads.browse.clear_all') }}</a>
                    @endif
                    <div class="ms-auto flex gap-1" data-view-toggle>
                        <button type="button" class="flex size-9 items-center justify-center rounded-lg text-stone-600 hover:bg-stone-100 aria-pressed:bg-brand-50 aria-pressed:text-brand-700" data-view="grid" aria-pressed="true" title="{{ __('ads.browse.view_grid') }}">{{ icon('layout-grid', 'size-5') }}<span class="sr-only">{{ __('ads.browse.view_grid') }}</span></button>
                        <button type="button" class="flex size-9 items-center justify-center rounded-lg text-stone-600 hover:bg-stone-100 aria-pressed:bg-brand-50 aria-pressed:text-brand-700" data-view="list" aria-pressed="false" title="{{ __('ads.browse.view_list') }}">{{ icon('list', 'size-5') }}<span class="sr-only">{{ __('ads.browse.view_list') }}</span></button>
                    </div>
                </div>

                @if (!$category)
                    <div class="mb-3 flex gap-2 overflow-x-auto pb-1">
                        @foreach ($categories as $main)
                            <a href="{{ lroute('browse.category', ['category' => $main['slug']]) }}" class="chip shrink-0 bg-white ring-stone-200">{{ icon($main['icon'], 'size-4') }} {{ translated($main['name']) }}</a>
                        @endforeach
                    </div>
                @endif

                @if ($results->isEmpty())
                    <div class="rounded-xl border border-dashed border-stone-300 bg-white p-10 text-center">
                        <p class="font-semibold">{{ __('ads.browse.nothing_title') }}</p>
                        <p class="mt-1 text-sm text-stone-600">{{ __('ads.browse.nothing_text') }}</p>
                    </div>
                @else
                    <div class="group/results grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4 data-[view=list]:grid-cols-1" data-results data-view="grid">
                        @foreach ($results as $ad)
                            @include('ads._card', ['ad' => $ad])
                        @endforeach
                    </div>
                    <div class="mt-6" data-pages>{{ $results->onEachSide(1)->links() }}</div>
                    @if (feature('lists.show_more') && $results->hasMorePages())
                        {{-- show-more.js shows this and hides the page links (they stay for Google and without JavaScript). --}}
                        <div class="mt-6 text-center" data-show-more hidden>
                            <button type="button" class="btn-primary w-full sm:w-auto sm:px-10" data-text="{{ __('ads.browse.show_more') }}" data-loading="{{ __('ads.browse.loading') }}"
                                    data-loaded-one="{{ trans_choice('ads.browse.loaded', 1) }}" data-loaded="{{ trans_choice('ads.browse.loaded', 2, ['count' => ':count']) }}">{{ __('ads.browse.show_more') }}</button>
                            <span class="sr-only" aria-live="polite"></span>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endsection
