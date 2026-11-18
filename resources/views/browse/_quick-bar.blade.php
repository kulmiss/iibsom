{{--
  Quick filters on top of the results (PR 77, switch "search.quick_bar"): Sort, Price, Condition and Area as small
  drop-downs. Every choice is a normal link (or a small GET form for a typed price), so it works without JavaScript
  and the address can be shared. The full filter panel stays for everything else. On phones only Sort shows here
  (Price, Condition and District are one tap away in the Filters sheet), so the bar stays one line.
--}}
@php
    // Only the filters this page really uses (old links can carry ones that are switched off).
    $more = feature('search.more_filters');
    $known = array_merge(['q', 'city', 'min', 'max', 'condition', 'sort', 'f'], $more ? ['area', 'posted', 'photos'] : [], $more && feature('verified_badge') ? ['verified'] : []);
    $query = \Illuminate\Support\Arr::only(request()->query(), $known);
    $sorts = array_merge(['newest', 'cheapest', 'expensive'], feature('ad_stats') && $more ? ['popular'] : []);
    if (! in_array($query['sort'] ?? 'newest', $sorts, true)) {
        unset($query['sort']);
    }
    $link = function (array $set, array $drop = []) use ($query): string {
        $params = array_merge(\Illuminate\Support\Arr::except($query, array_merge(array_keys($set), $drop)), $set);
        $params = array_filter($params, fn ($v) => $v !== null && $v !== '');

        return request()->url().($params ? '?'.http_build_query($params) : '');
    };
    // The other filters as hidden fields for the typed-price form (nested f[...] values too).
    $hidden = [];
    $rest = http_build_query(\Illuminate\Support\Arr::except($query, ['min', 'max']));
    foreach ($rest === '' ? [] : explode('&', $rest) as $pair) {
        [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
        $hidden[] = [urldecode($k), urldecode($v)];
    }
    $sort = in_array($filters['sort'] ?? 'newest', $sorts, true) ? ($filters['sort'] ?? 'newest') : 'newest';
    $hasPrice = ! empty($filters['min']) || ! empty($filters['max']);
    $condition = $filters['condition'] ?? '';
    $menu = 'absolute start-0 top-full z-30 mt-1 min-w-48 rounded-xl bg-white p-1.5 text-sm shadow-lg ring-1 ring-stone-200';
    $item = 'flex items-center justify-between gap-3 rounded-lg px-3 py-2 hover:bg-stone-100';
    $chip = 'btn-chip cursor-pointer list-none text-sm [&::-webkit-details-marker]:hidden';
@endphp
<div class="flex flex-wrap items-center gap-2" data-quick-bar>
    {{-- Sort --}}
    <details class="relative" data-quick>
        <summary class="{{ $chip }}">{{ icon('list', 'size-4') }} <span class="sr-only">{{ __('ads.browse.sort') }}:</span> {{ __('ads.browse.'.$sort) }} {{ icon('chevron-down', 'size-3.5') }}</summary>
        <ul class="{{ $menu }}" aria-label="{{ __('ads.browse.sort') }}">
            @foreach ($sorts as $s)
                <li><a href="{{ $link(['sort' => $s === 'newest' ? null : $s]) }}" class="{{ $item }} {{ $s === $sort ? 'font-semibold text-brand-700' : '' }}" @if ($s === $sort) aria-current="true" @endif>{{ __('ads.browse.'.$s) }} @if ($s === $sort){{ icon('check', 'size-4') }}@endif</a></li>
            @endforeach
        </ul>
    </details>

    {{-- Price --}}
    <details class="relative hidden sm:block" data-quick>
        <summary @class([$chip, '!bg-brand-50 !ring-brand-200' => $hasPrice])>{{ __('ads.browse.price') }} {{ icon('chevron-down', 'size-3.5') }}</summary>
        <div class="{{ $menu }} w-64 p-3">
            <form method="get" action="{{ request()->url() }}" class="space-y-2">
                @foreach ($hidden as [$k, $v])
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endforeach
                <div class="flex gap-2">
                    <input name="min" type="number" min="0" inputmode="numeric" value="{{ $filters['min'] ?? '' }}" placeholder="{{ __('ads.browse.min') }}" aria-label="{{ __('ads.browse.min') }}" class="form-input py-2">
                    <input name="max" type="number" min="0" inputmode="numeric" value="{{ $filters['max'] ?? '' }}" placeholder="{{ __('ads.browse.max') }}" aria-label="{{ __('ads.browse.max') }}" class="form-input py-2">
                </div>
                <button type="submit" class="btn-primary w-full justify-center">{{ __('ads.browse.apply') }}</button>
            </form>
            @if ($priceBuckets)
                <ul class="mt-2 border-t border-stone-100 pt-2">
                    @foreach ($priceBuckets as $b)
                        @php($label = $b['max'] === null ? __('ads.browse.price_over', ['from' => '$'.number_format($b['min'])]) : ($b['min'] === 0 ? __('ads.browse.price_under', ['to' => '$'.number_format($b['max'])]) : __('ads.browse.price_range', ['from' => '$'.number_format($b['min']), 'to' => '$'.number_format($b['max'])])))
                        <li><a href="{{ $link(['min' => $b['min'] ?: null, 'max' => $b['max'] ? $b['max'] - 1 : null]) }}" class="{{ $item }}"><span>{{ $label }}</span><span class="text-xs text-stone-500">{{ number_format($b['count']) }}</span></a></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </details>

    {{-- Condition --}}
    @if (! $category || $category->has_condition)
        <details class="relative hidden sm:block" data-quick>
            <summary @class([$chip, '!bg-brand-50 !ring-brand-200' => $condition !== ''])>{{ $condition !== '' ? __('ads.form.'.$condition) : __('ads.browse.condition') }} {{ icon('chevron-down', 'size-3.5') }}</summary>
            <ul class="{{ $menu }}" aria-label="{{ __('ads.browse.condition') }}">
                @foreach (['' => __('ads.browse.any'), 'new' => __('ads.form.new'), 'used' => __('ads.form.used')] as $value => $label)
                    <li><a href="{{ $link(['condition' => $value ?: null]) }}" class="{{ $item }} {{ $value === $condition ? 'font-semibold text-brand-700' : '' }}" @if ($value === $condition) aria-current="true" @endif>{{ $label }} @if ($value === $condition){{ icon('check', 'size-4') }}@endif</a></li>
                @endforeach
            </ul>
        </details>
    @endif

    {{-- Area (district) of the city --}}
    @if ($areas->isNotEmpty())
        <details class="relative hidden sm:block" data-quick>
            <summary @class([$chip, '!bg-brand-50 !ring-brand-200' => $area !== null])>{{ icon('map-pin', 'size-4') }} {{ $area?->label ?? __('ads.browse.all_areas') }} {{ icon('chevron-down', 'size-3.5') }}</summary>
            <ul class="{{ $menu }} max-h-72 overflow-y-auto" aria-label="{{ __('ads.form.area') }}">
                <li><a href="{{ $link([], ['area']) }}" class="{{ $item }} {{ $area === null ? 'font-semibold text-brand-700' : '' }}">{{ __('ads.browse.all_areas') }}</a></li>
                @foreach ($areas as $a)
                    <li><a href="{{ $link(['area' => $a->id]) }}" class="{{ $item }} {{ $area?->id === $a->id ? 'font-semibold text-brand-700' : '' }}" @if ($area?->id === $a->id) aria-current="true" @endif>{{ $a->label }}</a></li>
                @endforeach
            </ul>
        </details>
    @endif
</div>
