@extends('layouts.app')

@section('title', __('sellers.title', ['name' => $seller->name]))

@push('head')
    <meta name="robots" content="noindex, follow">
@endpush

@section('content')
    <section class="mx-auto max-w-6xl px-4 py-6">
        @php($plus = feature('sellers.page_plus'))
        @if ($plus)
            {{-- Cover band: our own calm pattern (PR 79); the seller's initial sits on its edge. --}}
            <div class="h-24 rounded-t-2xl bg-gradient-to-r from-brand-700 to-brand-900 sm:h-32" aria-hidden="true" data-seller-cover>
                <svg class="size-full opacity-20" preserveAspectRatio="none" viewBox="0 0 400 100" fill="none"><path d="M0 70 Q 50 40 100 70 T 200 70 T 300 70 T 400 70" stroke="#fff" stroke-width="2"/><path d="M0 85 Q 50 55 100 85 T 200 85 T 300 85 T 400 85" stroke="#fff" stroke-width="1.5"/></svg>
            </div>
        @endif
        <header @class(['flex flex-wrap items-center gap-4 bg-white p-5 shadow-sm ring-1 ring-stone-200', 'rounded-2xl' => ! $plus, 'rounded-b-2xl' => $plus])>
            <span @class(['flex size-14 shrink-0 items-center justify-center rounded-full bg-brand-700 text-xl font-bold text-white', '-mt-12 size-20 text-3xl ring-4 ring-(--surface) sm:-mt-14' => $plus])>{{ $seller->initial() }}</span>
            <div class="min-w-0 flex-1">
                <h1 class="flex min-w-0 flex-wrap items-center gap-2 text-xl font-bold">
                    <span class="break-words">{{ $seller->name }}</span>
                    @if (feature('verified_badge') && $seller->isVerified())
                        @include('partials.verified-badge')
                    @endif
                </h1>
                <p class="text-sm text-stone-600">
                    @if ($seller->city){{ $seller->city->label }} · @endif{{ __('ads.page.member_since', ['date' => mdate($seller->created_at, 'M Y')]) }}
                    · {{ trans_choice('sellers.live_ads', $total, ['count' => $total]) }}
                    @if ($followers > 0) · {{ trans_choice('sellers.followers', $followers, ['count' => $followers]) }}@endif
                </p>
                @feature('sellers.badges')
                    @php($tenure = \App\Support\Users\SellerBadges::tenure($seller))
                    @php($fast = \App\Support\Users\SellerBadges::repliesFast($seller))
                    @if ($tenure || $fast)
                        <ul class="mt-1.5 flex flex-wrap gap-1.5 text-xs font-medium">
                            @if ($tenure)
                                <li class="flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 text-brand-800">{{ icon('clock', 'size-3.5') }} {{ trans_choice('ads.badges.'.$tenure['unit'], $tenure['count'], ['count' => $tenure['count']]) }}</li>
                            @endif
                            @if ($fast)
                                <li class="flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-800" title="{{ __('ads.badges.fast_hint') }}">{{ icon('zap', 'size-3.5') }} {{ __('ads.badges.fast') }}</li>
                            @endif
                        </ul>
                    @endif
                @endfeature
                @include('partials.seller-rating', ['seller' => $seller])
            </div>
            <div class="w-full sm:w-auto">@include('sellers._follow-button', ['seller' => $seller, 'following' => $following])</div>
        </header>

        <div class="mt-6 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-semibold">{{ __('sellers.all_ads') }}</h2>
            @if ($plus && $total > 1)
                <nav class="flex gap-1 text-sm" aria-label="{{ __('ads.browse.sort') }}" data-seller-sort>
                    @foreach (['newest', 'cheapest', 'expensive'] as $s)
                        <a href="{{ request()->url().'?'.http_build_query(array_filter(['c' => $category['slug'] ?? null, 'sort' => $s === 'newest' ? null : $s])) }}"
                           @class(['rounded-full px-3 py-1.5 font-medium', 'bg-brand-700 text-white' => $sort === $s, 'text-stone-700 hover:bg-stone-100' => $sort !== $s]) @if ($sort === $s) aria-current="true" @endif>{{ __('ads.browse.'.$s) }}</a>
                    @endforeach
                </nav>
            @endif
        </div>
        @if ($plus && $facets)
            <nav class="-mx-4 mt-3 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="{{ __('ads.browse.categories') }}" data-seller-facets>
                <ul class="flex gap-2 sm:flex-wrap">
                    <li class="shrink-0"><a href="{{ request()->url().($sort !== 'newest' ? '?sort='.$sort : '') }}" @class(['btn-chip text-sm', '!bg-brand-700 !text-white !ring-brand-700' => $category === null]) @if ($category === null) aria-current="true" @endif>{{ __('ads.browse.all_categories') }} <span @class(['text-stone-500' => $category !== null])>{{ $total }}</span></a></li>
                    @foreach ($facets as $f)
                        <li class="shrink-0"><a href="{{ request()->url().'?'.http_build_query(array_filter(['c' => $f['slug'], 'sort' => $sort === 'newest' ? null : $sort])) }}" @class(['btn-chip text-sm', '!bg-brand-700 !text-white !ring-brand-700' => ($category['slug'] ?? null) === $f['slug']]) @if (($category['slug'] ?? null) === $f['slug']) aria-current="true" @endif>{{ icon($f['icon'], 'size-4') }} {{ $f['label'] }} <span @class(['text-stone-500' => ($category['slug'] ?? null) !== $f['slug']])>{{ $f['count'] }}</span></a></li>
                    @endforeach
                </ul>
            </nav>
        @endif
        @if ($ads->isEmpty())
            <x-empty-state class="mt-3" icon="store" :title="__('sellers.none')" />
        @else
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                @foreach ($ads as $ad)
                    @include('ads._card', ['ad' => $ad])
                @endforeach
            </div>
            <div class="mt-6">{{ $ads->links() }}</div>
        @endif
        @if ($plus && feature('site.safety_box'))
            <p class="mt-6 flex items-start gap-2 rounded-xl bg-brand-50 p-3 text-sm text-stone-700 ring-1 ring-brand-200">{{ icon('shield-check', 'size-5 shrink-0 text-brand-700') }}
                <span>{{ __('ads.page.safety_1') }} {{ __('ads.page.safety_2') }} <a href="{{ lroute('page', ['slug' => 'safety']) }}" class="font-medium text-brand-700 underline">{{ __('ads.page.safety_more') }}</a></span></p>
        @endif
    </section>
@endsection
