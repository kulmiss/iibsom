@extends('layouts.app')

@section('title', __('sellers.title', ['name' => $seller->name]))

@push('head')
    <meta name="robots" content="noindex, follow">
@endpush

@section('content')
    <section class="mx-auto max-w-6xl px-4 py-6">
        <header class="flex flex-wrap items-center gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            <span class="flex size-14 shrink-0 items-center justify-center rounded-full bg-brand-700 text-xl font-bold text-white">{{ $seller->initial() }}</span>
            <div class="min-w-0 flex-1">
                <h1 class="flex min-w-0 flex-wrap items-center gap-2 text-xl font-bold">
                    <span class="break-words">{{ $seller->name }}</span>
                    @if (feature('verified_badge') && $seller->isVerified())
                        @include('partials.verified-badge')
                    @endif
                </h1>
                <p class="text-sm text-stone-600">
                    @if ($seller->city){{ $seller->city->label }} · @endif{{ __('ads.page.member_since', ['date' => mdate($seller->created_at, 'M Y')]) }}
                    · {{ trans_choice('sellers.live_ads', $ads->total(), ['count' => $ads->total()]) }}
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

        <h2 class="mt-6 text-lg font-semibold">{{ __('sellers.all_ads') }}</h2>
        @if ($ads->isEmpty())
            <div class="mt-3 rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center text-stone-600">{{ __('sellers.none') }}</div>
        @else
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                @foreach ($ads as $ad)
                    @include('ads._card', ['ad' => $ad])
                @endforeach
            </div>
            <div class="mt-6">{{ $ads->links() }}</div>
        @endif
    </section>
@endsection
