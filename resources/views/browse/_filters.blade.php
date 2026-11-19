{{--
  Filters: a left sidebar on computers, a full-screen sheet on phones (opened by the "Filters" button = a link
  to #filters, so it works with no JavaScript). Every filter is in the address, so a search can be shared.
  filters.js only adds "Show 120 results" while you change things.
--}}
@php
    $mainRow = $category ? collect($categories)->firstWhere('id', $category->parent_id ?? $category->id) : null;
    // "Hide empty sub categories" applies here too, but the page you are on always shows.
    $visibleIds = $mainRow ? array_column(app(\App\Support\Catalog::class)->visibleChildren($mainRow), 'id') : [];
    $subs = $mainRow ? array_filter($mainRow['children'], fn ($c) => $c['id'] === $category->id || in_array($c['id'], $visibleIds, true)) : [];
    $catUrl = fn (string $slug) => lroute('browse.category', array_filter(['category' => $slug, 'city' => $city?->slug]));
    $q = $filters['q'] ?? null;
    $withQ = fn (string $url) => $q ? $url.'?'.http_build_query(['q' => $q]) : $url;
    $byRegion = collect($cities)->groupBy(fn ($c) => translated($c['region']));
    $cityUrl = fn (?string $slug) => $category
        ? lroute('browse.category', array_filter(['category' => $category->slug, 'city' => $slug]))
        : lroute('search', array_filter(['q' => $q, 'city' => $slug]));
    $countUrl = ! feature('search.more_filters') ? '' : lroute('browse.count', array_filter(['category' => $category?->slug, 'city' => $city?->slug, 'brand' => $brand['value'] ?? null]));
@endphp
<section id="filters" class="hidden target:fixed target:inset-0 target:z-50 target:flex target:flex-col target:bg-(--surface) lg:static lg:block lg:bg-transparent" aria-label="{{ __('ads.browse.filters') }}">
    {{-- Phone sheet header --}}
    <div class="flex items-center justify-between border-b border-stone-200 px-4 py-3 lg:hidden">
        <p class="text-lg font-semibold">{{ __('ads.browse.filters') }}</p>
        <a href="#results" class="flex size-10 items-center justify-center rounded-full hover:bg-stone-100" aria-label="{{ __('ads.browse.close') }}">{{ icon('x', 'size-5') }}</a>
    </div>

    <form method="get" action="{{ $action }}" class="min-h-0 flex-1 scroll-pb-32 space-y-5 overflow-y-auto p-4 lg:overflow-visible lg:rounded-xl lg:bg-(--surface) lg:shadow-sm lg:ring-1 lg:ring-stone-200" data-filter-form data-count-url="{{ $countUrl }}">
        @if (!$category)
            <input type="hidden" name="q" value="{{ $q ?? '' }}">
            @if ($city)<input type="hidden" name="city" value="{{ $city->slug }}">@endif
        @elseif (feature('site.header_search'))
            {{-- The header already has "Search in Cars" (PR 77: no second search box here). --}}
            <input type="hidden" name="q" value="{{ $q ?? '' }}">
        @else
            <div>
                <label for="fq" class="form-label">{{ __('site.home.search_button') }}</label>
                <input id="fq" name="q" type="search" value="{{ $q ?? '' }}" class="form-input">
            </div>
        @endif

        {{-- Category tree: the main category, its sub categories, and a way back to all --}}
        <div>
            <p class="form-label">{{ __('ads.browse.categories') }}</p>
            <ul class="space-y-0.5 text-sm">
                @if ($mainRow)
                    <li><a href="{{ $withQ($catUrl($mainRow['slug'])) }}" @class(['flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-stone-100', 'font-semibold text-brand-800' => $category->parent_id === null])>{{ icon($mainRow['icon'], 'size-4') }} {{ translated($mainRow['name']) }}</a></li>
                    @foreach ($subs as $sub)
                        <li><a href="{{ $withQ($catUrl($sub['slug'])) }}" @class(['flex items-center justify-between gap-2 rounded-md py-1.5 ps-8 pe-2 hover:bg-stone-100', 'bg-brand-50 font-semibold text-brand-800' => $sub['id'] === $category->id]) @if ($sub['id'] === $category->id) aria-current="page" @endif>
                            <span>{{ translated($sub['name']) }}</span><span class="text-xs text-stone-500">{{ number_format($sub['ads_count']) }}</span></a></li>
                    @endforeach
                    <li><a href="{{ lroute('search', array_filter(['q' => $q, 'city' => $city?->slug])) }}" class="block px-2 py-1.5 text-brand-700 underline">{{ __('ads.browse.show_all_categories') }}</a></li>
                @else
                    @foreach ($categories as $main)
                        <li><a href="{{ $withQ($catUrl($main['slug'])) }}" class="flex items-center justify-between gap-2 rounded-md px-2 py-1.5 hover:bg-stone-100"><span class="flex items-center gap-2">{{ icon($main['icon'], 'size-4') }} {{ translated($main['name']) }}</span><span class="text-xs text-stone-500">{{ number_format($main['ads_count']) }}</span></a></li>
                    @endforeach
                @endif
            </ul>
        </div>

        {{-- Location: region → city → area --}}
        @if (count($cities) > 1 || $areas->isNotEmpty())
            <div>
                <p class="form-label">{{ __('ads.browse.location') }}</p>
                @if (count($cities) > 1)
                    <ul class="space-y-0.5 text-sm">
                        <li><a href="{{ $cityUrl(null) }}" @class(['block rounded-md px-2 py-1.5 hover:bg-stone-100', 'font-semibold text-brand-800' => !$city])>{{ __('ads.browse.somalia') }}</a></li>
                        @foreach ($byRegion as $regionName => $regionCities)
                            <li class="px-2 pt-1 text-xs font-semibold uppercase tracking-wide text-stone-500">{{ $regionName }}</li>
                            @foreach ($regionCities as $c)
                                <li><a href="{{ $cityUrl($c['slug']) }}" @class(['block rounded-md py-1.5 ps-5 pe-2 hover:bg-stone-100', 'bg-brand-50 font-semibold text-brand-800' => $city?->id === $c['id']])>{{ translated($c['name']) }}</a></li>
                            @endforeach
                        @endforeach
                    </ul>
                @endif
                @if ($areas->isNotEmpty())
                    <label for="farea" class="sr-only">{{ __('ads.form.area') }}</label>
                    <select id="farea" name="area" class="form-input mt-2">
                        <option value="">{{ __('ads.browse.all_areas') }}</option>
                        @foreach ($areas as $a)
                            <option value="{{ $a->id }}" @selected($area?->id === $a->id)>{{ $a->label }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
        @endif

        {{-- Price + quick ranges with how many ads each has --}}
        <div>
            <p class="form-label">{{ __('ads.browse.price') }}</p>
            <div class="flex gap-2">
                <input name="min" type="number" min="0" inputmode="numeric" placeholder="{{ __('ads.browse.min') }}" value="{{ $filters['min'] ?? '' }}" class="form-input" aria-label="{{ __('ads.browse.min') }}">
                <input name="max" type="number" min="0" inputmode="numeric" placeholder="{{ __('ads.browse.max') }}" value="{{ $filters['max'] ?? '' }}" class="form-input" aria-label="{{ __('ads.browse.max') }}">
            </div>
            @if ($priceBuckets)
                <ul class="mt-2 space-y-0.5 text-sm">
                    @foreach ($priceBuckets as $b)
                        @php($params = array_merge(\Illuminate\Support\Arr::except(request()->query(), ['min', 'max', 'page']), ['min' => $b['min'] ?: null, 'max' => $b['max'] ? $b['max'] - 1 : null]))
                        @php($label = $b['max'] === null ? __('ads.browse.price_over', ['from' => '$'.number_format($b['min'])]) : ($b['min'] === 0 ? __('ads.browse.price_under', ['to' => '$'.number_format($b['max'])]) : __('ads.browse.price_range', ['from' => '$'.number_format($b['min']), 'to' => '$'.number_format($b['max'])])))
                        <li><a href="{{ request()->url().'?'.http_build_query(array_filter($params, fn ($v) => $v !== null && $v !== '')) }}" class="flex justify-between rounded-md px-2 py-1 hover:bg-stone-100"><span>{{ $label }}</span><span class="text-xs text-stone-500">{{ number_format($b['count']) }}</span></a></li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if (!$category || $category->has_condition)
            <fieldset>
                <legend class="form-label">{{ __('ads.browse.condition') }}</legend>
                <div class="flex flex-wrap gap-2 text-sm">
                    @foreach (['' => __('ads.browse.any'), 'new' => __('ads.form.new'), 'used' => __('ads.form.used')] as $value => $label)
                        <label class="btn-chip cursor-pointer has-[:checked]:!bg-brand-700 has-[:checked]:!text-white has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-600"><input type="radio" name="condition" value="{{ $value }}" class="sr-only" @checked(($filters['condition'] ?? '') === $value)> {{ $label }}</label>
                    @endforeach
                </div>
            </fieldset>
        @endif

        @feature('search.more_filters')
        <fieldset>
            <legend class="form-label">{{ __('ads.browse.posted') }}</legend>
            <div class="space-y-1 text-sm">
                @foreach (['' => __('ads.browse.posted_any'), '24h' => __('ads.browse.posted_24h'), '7d' => __('ads.browse.posted_7d'), '30d' => __('ads.browse.posted_30d')] as $value => $label)
                    <label class="flex items-center gap-2"><input type="radio" name="posted" value="{{ $value }}" class="size-4" @checked(($filters['posted'] ?? '') === $value)> {{ $label }}</label>
                @endforeach
            </div>
        </fieldset>

        <div class="space-y-2 text-sm">
            <label class="flex items-center gap-2 font-medium"><input type="checkbox" name="photos" value="1" class="size-4 rounded border-stone-300" @checked(!empty($filters['photos']))> {{ __('ads.browse.with_photos') }}</label>
            @feature('verified_badge')
                <label class="flex items-center gap-2 font-medium"><input type="checkbox" name="verified" value="1" class="size-4 rounded border-stone-300" @checked(!empty($filters['verified']))> {{ __('ads.browse.verified_only') }}</label>
            @endfeature
        </div>
        @endfeature

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

        <div @class(['lg:hidden' => feature('search.quick_bar')]) data-panel-sort>
            <label for="fsort" class="form-label">{{ __('ads.browse.sort') }}</label>
            <select id="fsort" name="sort" class="form-input">
                @foreach (array_merge(['newest', 'cheapest', 'expensive'], feature('ad_stats') && feature('search.more_filters') ? ['popular'] : []) as $s)
                    <option value="{{ $s }}" @selected(($filters['sort'] ?? 'newest') === $s)>{{ __('ads.browse.'.$s) }}</option>
                @endforeach
            </select>
        </div>

        {{-- Bottom bar of the phone sheet; normal buttons on computers --}}
        <div class="sticky bottom-0 -mx-4 -mb-4 space-y-2 border-t border-stone-200 bg-white p-4 lg:static lg:m-0 lg:border-0 lg:p-0">
            <button type="submit" class="btn-primary w-full justify-center py-3" data-count-text>{{ trans_choice('ads.browse.show_results', $results->total(), ['count' => number_format($results->total())]) }}</button>
            <a href="{{ $action }}" class="block text-center text-sm text-stone-600 underline">{{ __('ads.browse.clear') }}</a>
        </div>
    </form>
</section>
