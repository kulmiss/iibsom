{{-- Stars next to a seller (switch "seller_ratings"). Needs $seller. --}}
@if (feature('seller_ratings') && $seller->rating() !== null)
    <a href="{{ lroute('seller.reviews', ['user' => $seller->id]) }}" class="inline-flex items-center gap-1 text-sm font-medium text-stone-700 hover:underline"
       aria-label="{{ __('ads.ratings.average', ['rating' => number_format((float) $seller->rating(), 1)]) }}, {{ trans_choice('ads.ratings.count', $seller->rating_count, ['count' => $seller->rating_count]) }}">
        {{ icon('star', 'size-4 fill-amber-400 text-amber-500') }}
        <span>{{ number_format((float) $seller->rating(), 1) }}</span>
        <span class="text-stone-500">({{ trans_choice('ads.ratings.count', $seller->rating_count, ['count' => $seller->rating_count]) }})</span>
    </a>
@endif
