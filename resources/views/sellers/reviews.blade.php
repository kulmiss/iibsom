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
                    <li id="review-{{ $review->id }}" class="scroll-mt-20 rounded-2xl bg-white p-4 ring-1 ring-stone-200" data-review>
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

                        @feature('ratings.replies')
                            @if ($review->reply !== null)
                                {{-- The seller's one public answer (PR 85). --}}
                                <div class="mt-3 rounded-xl bg-stone-50 p-3 ring-1 ring-stone-200" data-review-reply>
                                    <p class="text-xs font-semibold text-stone-600">{{ __('ads.ratings.reply_from', ['name' => \Illuminate\Support\Str::before($seller->name, ' ') ?: $seller->name]) }} · {{ mdate($review->replied_at) }}</p>
                                    <p class="mt-1 whitespace-pre-line break-words text-sm text-stone-800">{{ $review->reply }}</p>
                                </div>
                            @elseif ($viewer?->id === $seller->id)
                                <details class="mt-3" @if ($errors->has('reply') && old('review_id') == $review->id) open @endif>
                                    <summary class="inline-flex cursor-pointer list-none items-center gap-1.5 text-sm font-semibold text-brand-800 [&::-webkit-details-marker]:hidden">{{ icon('message-circle', 'size-4') }} {{ __('ads.ratings.reply_button') }}</summary>
                                    <form method="post" action="{{ lroute('review.reply', ['review' => $review->id]) }}" class="mt-2" data-review-reply-form>
                                        @csrf
                                        <input type="hidden" name="review_id" value="{{ $review->id }}">
                                        <label for="reply-{{ $review->id }}" class="text-sm font-medium">{{ __('ads.ratings.reply_label') }}</label>
                                        <textarea id="reply-{{ $review->id }}" name="reply" rows="3" required maxlength="{{ \App\Models\SellerReview::COMMENT_MAX }}" class="form-input mt-1"
                                                  aria-describedby="reply-help-{{ $review->id }}">{{ old('review_id') == $review->id ? old('reply') : '' }}</textarea>
                                        <p id="reply-help-{{ $review->id }}" class="mt-1 text-xs text-stone-500">{{ __('ads.ratings.reply_help') }}</p>
                                        @if (old('review_id') == $review->id)
                                            @error('reply')<p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                                        @endif
                                        <button type="submit" class="btn-primary mt-2">{{ __('ads.ratings.reply_send') }}</button>
                                    </form>
                                </details>
                            @endif
                        @endfeature

                        @feature('ratings.likes')
                            {{-- "Helpful" (PR 85): once per person; not the seller or the buyer who wrote it. --}}
                            <div class="mt-3 flex items-center gap-2 text-sm">
                                @if ($viewer !== null && $viewer->id !== $review->seller_id && $viewer->id !== $review->buyer_id)
                                    @php($on = in_array($review->id, $liked, true))
                                    <form method="post" action="{{ lroute('review.like', ['review' => $review->id]) }}" data-review-like>
                                        @csrf
                                        <button type="submit" aria-pressed="{{ $on ? 'true' : 'false' }}"
                                                class="chip gap-1.5 ring-stone-200 aria-pressed:bg-brand-50 aria-pressed:text-brand-800 aria-pressed:ring-brand-200 aria-pressed:[&_svg]:fill-current">
                                            {{ icon('thumbs-up', 'size-4') }}<span>{{ __('ads.ratings.helpful') }}</span>
                                            <span class="text-stone-500" data-like-count>{{ $review->likes_count > 0 ? $review->likes_count : '' }}</span>
                                        </button>
                                    </form>
                                @elseif ($viewer === null && feature('login.google'))
                                    <a href="{{ lroute('login') }}" class="chip gap-1.5 ring-stone-200" title="{{ __('ads.ratings.helpful_sign_in') }}">{{ icon('thumbs-up', 'size-4') }}<span>{{ __('ads.ratings.helpful') }}</span>@if ($review->likes_count > 0)<span class="text-stone-500">{{ $review->likes_count }}</span>@endif</a>
                                @elseif ($review->likes_count > 0)
                                    <span class="inline-flex items-center gap-1.5 text-stone-600">{{ icon('thumbs-up', 'size-4') }} {{ trans_choice('ads.ratings.helpful_count', $review->likes_count, ['count' => $review->likes_count]) }}</span>
                                @endif
                            </div>
                        @endfeature
                    </li>
                @endforeach
            </ul>
            <div class="mt-6">{{ $reviews->links() }}</div>
        @endif
    </section>
@endsection
