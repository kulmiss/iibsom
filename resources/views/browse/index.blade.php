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
    @if ($results->currentPage() > 1 || !empty($filters['q']) || $extraFilters || !empty($filters['min']) || !empty($filters['max']) || !empty($filters['condition']) || $results->total() === 0)
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

        <div class="mt-4 grid gap-5 lg:grid-cols-[16rem_1fr]">
            {{-- Filters: a fold-out box on phones, a sidebar on desktop --}}
            <details class="group rounded-xl bg-white shadow-sm ring-1 ring-stone-200 lg:open:block" @if (request()->hasAny(['min','max','condition','f'])) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 font-semibold lg:hidden">
                    {{ __('ads.browse.filters') }} {{ icon('chevron-down', 'size-5 transition group-open:rotate-180') }}
                </summary>
                <form method="get" action="{{ $action }}" class="space-y-4 border-t border-stone-100 p-4 lg:border-0">
                    @if (!$category)
                        <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
                    @else
                        <div>
                            <label for="fq" class="form-label">{{ __('site.home.search_button') }}</label>
                            <input id="fq" name="q" type="search" value="{{ $filters['q'] ?? '' }}" class="form-input">
                        </div>
                    @endif

                    @if (count($cities) > 1 && $category)
                        <div>
                            <p class="form-label">{{ __('ads.browse.city') }}</p>
                            <ul class="space-y-1 text-sm">
                                <li><a href="{{ lroute('browse.category', ['category' => $category->slug]) }}" @class(['font-semibold text-brand-700' => !$city])>{{ __('ads.browse.all_cities') }}</a></li>
                                @foreach ($cities as $c)
                                    <li><a href="{{ lroute('browse.category', ['category' => $category->slug, 'city' => $c['slug']]) }}" @class(['font-semibold text-brand-700' => $city?->id === $c['id']])>{{ translated($c['name']) }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div>
                        <p class="form-label">{{ __('ads.browse.price') }}</p>
                        <div class="flex gap-2">
                            <input name="min" type="number" min="0" inputmode="numeric" placeholder="{{ __('ads.browse.min') }}" value="{{ $filters['min'] ?? '' }}" class="form-input" aria-label="{{ __('ads.browse.min') }}">
                            <input name="max" type="number" min="0" inputmode="numeric" placeholder="{{ __('ads.browse.max') }}" value="{{ $filters['max'] ?? '' }}" class="form-input" aria-label="{{ __('ads.browse.max') }}">
                        </div>
                    </div>

                    @if (!$category || $category->has_condition)
                        <div>
                            <label for="fcond" class="form-label">{{ __('ads.browse.condition') }}</label>
                            <select id="fcond" name="condition" class="form-input">
                                <option value="">{{ __('ads.browse.any') }}</option>
                                <option value="new" @selected(($filters['condition'] ?? '') === 'new')>{{ __('ads.form.new') }}</option>
                                <option value="used" @selected(($filters['condition'] ?? '') === 'used')>{{ __('ads.form.used') }}</option>
                            </select>
                        </div>
                    @endif

                    @foreach ($fields as $field)
                        @php($v = $filters['f'][$field->key] ?? null)
                        <div>
                            @if ($field->type === 'number')
                                <p class="form-label">{{ $field->label_text }}@if ($field->unit) ({{ $field->unit }})@endif</p>
                                <div class="flex gap-2">
                                    <input name="f[{{ $field->key }}][min]" type="number" min="0" placeholder="{{ __('ads.browse.from') }}" value="{{ is_array($v) ? ($v['min'] ?? '') : '' }}" class="form-input" aria-label="{{ $field->label_text }} {{ __('ads.browse.from') }}">
                                    <input name="f[{{ $field->key }}][max]" type="number" min="0" placeholder="{{ __('ads.browse.to') }}" value="{{ is_array($v) ? ($v['max'] ?? '') : '' }}" class="form-input" aria-label="{{ $field->label_text }} {{ __('ads.browse.to') }}">
                                </div>
                            @elseif ($field->type === 'select')
                                <label for="ff-{{ $field->key }}" class="form-label">{{ $field->label_text }}</label>
                                <select id="ff-{{ $field->key }}" name="f[{{ $field->key }}]" class="form-input">
                                    <option value="">{{ __('ads.browse.any') }}</option>
                                    @foreach ($field->optionList() as $ov => $ol)
                                        <option value="{{ $ov }}" @selected($v === $ov)>{{ $ol }}</option>
                                    @endforeach
                                </select>
                            @elseif ($field->type === 'boolean')
                                <label class="flex items-center gap-2 text-sm font-medium">
                                    <input type="checkbox" name="f[{{ $field->key }}]" value="1" @checked($v === '1') class="size-4 rounded border-stone-300"> {{ $field->label_text }}
                                </label>
                            @endif
                        </div>
                    @endforeach

                    <div>
                        <label for="fsort" class="form-label">{{ __('ads.browse.sort') }}</label>
                        <select id="fsort" name="sort" class="form-input">
                            @foreach (['newest', 'cheapest', 'expensive'] as $s)
                                <option value="{{ $s }}" @selected(($filters['sort'] ?? 'newest') === $s)>{{ __('ads.browse.'.$s) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="btn-primary w-full justify-center py-2.5">{{ __('ads.browse.apply') }}</button>
                    <a href="{{ $action }}" class="block text-center text-sm text-stone-600 underline">{{ __('ads.browse.clear') }}</a>
                </form>
            </details>

            <div class="min-w-0">
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
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                        @foreach ($results as $ad)
                            @include('ads._card', ['ad' => $ad])
                        @endforeach
                    </div>
                    <div class="mt-6">{{ $results->onEachSide(1)->links() }}</div>
                @endif
            </div>
        </div>
    </div>
@endsection
