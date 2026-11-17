@extends('layouts.app')

@section('title', __('ads.ratings.title', ['name' => $seller->name]))

@push('head')
    <meta name="robots" content="noindex, follow">
@endpush

@section('content')
    <section class="mx-auto max-w-2xl px-4 py-8">
        <header class="flex items-center gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            <span class="flex size-14 shrink-0 items-center justify-center rounded-full bg-brand-700 text-xl font-bold text-white">{{ $seller->initial() }}</span>
            <div class="min-w-0">
                <h1 class="flex min-w-0 flex-wrap items-center gap-2 text-xl font-bold">
                    <span class="break-words">{{ __('ads.ratings.title', ['name' => $seller->name]) }}</span>
                    @if (feature('verified_badge') && $seller->isVerified())
                        @include('partials.verified-badge')
                    @endif
                </h1>
                <p class="mt-1 flex items-center gap-2 text-lg font-semibold">
                    @include('partials.stars', ['stars' => (int) round((float) $seller->rating())])
                    {{ __('ads.ratings.average', ['rating' => number_format((float) $seller->rating(), 1)]) }}
                </p>
                <p class="text-sm text-stone-600">
                    {{ trans_choice('ads.ratings.count', $seller->rating_count, ['count' => $seller->rating_count]) }}
                    @if ($seller->city) · {{ $seller->city->label }}@endif
                    · {{ trans_choice('ads.page.seller_ads', max(1, $liveAds), ['count' => $liveAds]) }}
                </p>
            </div>
        </header>

        <p class="mt-4 text-sm text-stone-600">{{ __('ads.ratings.from_buyers') }}</p>

        @if ($reviews->isEmpty())
            <p class="mt-6 text-stone-600">{{ __('ads.ratings.none') }}</p>
        @else
            <ul class="mt-4 space-y-3">
                @foreach ($reviews as $review)
                    <li class="rounded-2xl bg-white p-4 ring-1 ring-stone-200">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            @include('partials.stars', ['stars' => $review->stars])
                            <span class="text-xs text-stone-500">{{ \Illuminate\Support\Str::before($review->buyer->name, ' ') ?: $review->buyer->name }} · {{ mdate($review->created_at) }}</span>
                        </div>
                        @if ($review->comment)
                            <p class="mt-2 whitespace-pre-line break-words text-stone-800">{{ $review->comment }}</p>
                        @endif
                        @if ($review->ad)
                            <p class="mt-1 truncate text-xs text-stone-500">{{ __('ads.ratings.about', ['title' => $review->ad->title]) }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
            <div class="mt-6">{{ $reviews->links() }}</div>
        @endif
    </section>
@endsection
