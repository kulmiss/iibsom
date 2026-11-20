{{-- One ad in a grid (home, search, similar ads). Lazy photo, AVIF/WebP.
     The same HTML for everyone (guest pages are cached): the Save button gets its state from card-save.js. --}}
@php
    $details = feature('ads.card_details');
    $canSave = feature('favourites') && feature('ads.card_save');
    $cover = $ad->cover;
    $badges = feature('ads.card_badges');
    $photoCount = $badges ? (int) ($ad->ready_photos_count ?? 0) : 0;
    $popular = $badges && feature('ad_stats') && (int) $ad->views >= config('iibsom.popular_views');
@endphp
{{-- "isolate": the photo labels and the Save button stay inside the card's own layer, so menus and side panels
     (the sticky category list on home, PR 91) are never drawn under them. --}}
<div class="relative isolate" data-card>
<a href="{{ $ad->url() }}" class="group flex h-full flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-stone-200 hover:ring-brand-200 group-data-[view=list]/results:flex-row">
    <div class="relative aspect-[4/3] bg-stone-100 group-data-[view=list]/results:w-40 group-data-[view=list]/results:shrink-0 sm:group-data-[view=list]/results:w-52">
        @if ($boost = $ad->activeBoost())
            <span class="absolute start-2 top-2 z-10 rounded-full px-2 py-0.5 text-xs font-bold shadow-sm {{ $boost === 3 ? 'bg-sky-900 text-white' : ($boost === 2 ? 'bg-amber-400 text-stone-950' : 'bg-brand-700 text-white') }}">{{ \App\Support\Money\Boosts::label($boost) }}</span>
        @endif
        @if ($photoCount >= 2)
            <span class="absolute end-2 bottom-2 z-10 flex items-center gap-1 rounded-full bg-stone-950/70 px-1.5 py-0.5 text-xs font-semibold text-white" title="{{ trans_choice('ads.badges.photos', $photoCount, ['count' => $photoCount]) }}">{{ icon('camera', 'size-3.5') }}<span aria-hidden="true">{{ $photoCount }}</span><span class="sr-only">{{ trans_choice('ads.badges.photos', $photoCount, ['count' => $photoCount]) }}</span></span>
        @endif
        @if ($popular)
            <span class="absolute start-2 bottom-2 z-10 flex items-center gap-1 rounded-full bg-white/95 px-2 py-0.5 text-xs font-bold text-orange-700 shadow-sm">{{ icon('flame', 'size-3.5') }} {{ __('ads.badges.popular') }}</span>
        @endif
        @if ($cover)
            <picture>
                @if ($avif = $cover->url('thumb', 'avif'))
                    <source type="image/avif" srcset="{{ $avif }}">
                @endif
                {{-- $eager: the first cards of a list are on screen at once (often the biggest thing = LCP), so they load
                     straight away; the rest wait until they are scrolled near. --}}
                <img src="{{ $cover->url('thumb') }}" alt="{{ $ad->title }}" @if ($eager ?? false) fetchpriority="high" @else loading="lazy" decoding="async" @endif
                     width="400" height="300" class="size-full object-cover">
            </picture>
        @else
            {{-- No photo: a calm tinted box with the category icon, so the grid still looks tidy. --}}
            <span class="flex size-full flex-col items-center justify-center gap-1.5 bg-gradient-to-br from-brand-50 to-stone-100 text-brand-300" data-no-photo>{{ icon($ad->category->icon ?? 'tag', 'size-10') }}<span class="text-xs font-medium text-stone-500">{{ __('ads.page.no_photo') }}</span></span>
        @endif
    </div>
    <div class="flex min-w-0 flex-1 flex-col gap-0.5 p-2.5">
        <p class="font-bold text-brand-800">{{ $ad->priceLabel() ?: __('ads.page.negotiable') }}</p>
        <p class="line-clamp-2 min-h-[2lh] text-sm leading-snug group-hover:underline group-data-[view=list]/results:min-h-0" data-card-title>{{ $ad->title }}</p>
        @if ($details && filled($ad->description))
            {{-- List view only: the first words of the description. --}}
            <div class="hidden group-data-[view=list]/results:block"><p class="line-clamp-2 text-sm text-stone-600">{{ \Illuminate\Support\Str::limit(preg_replace('/\s+/u', ' ', (string) $ad->description) ?? '', 160) }}</p></div>
        @endif
        {{-- About the seller (PR 85 stars, PR 87 years): loaded for the whole list in one query (withCardSeller), never one per card. --}}
        @php($cardSeller = $ad->relationLoaded('cardSeller') ? $ad->cardSeller : null)
        @if ($cardSeller && \App\Models\Ad::cardStarsOn() && ($stars = $cardSeller->rating()) !== null)
            <p class="flex items-center gap-1 text-xs text-stone-600" data-card-stars>
                {{ icon('star', 'size-3.5 fill-amber-400 text-amber-500') }}<span aria-hidden="true">{{ number_format($stars, 1) }} ({{ $cardSeller->rating_count }})</span>
                <span class="sr-only">{{ trans_choice('ads.ratings.seller_stars', $cardSeller->rating_count, ['rating' => number_format($stars, 1), 'count' => $cardSeller->rating_count]) }}</span>
            </p>
        @endif
        @if ($cardSeller && \App\Models\Ad::cardYearsOn() && ($tenure = \App\Support\Users\SellerBadges::tenure($cardSeller)) !== null && $tenure['unit'] === 'years')
            {{-- Years only on cards (months would be noise in a list). --}}
            <p class="flex items-center gap-1 text-xs text-stone-600" data-card-years>{{ icon('user-check', 'size-3.5 text-brand-700') }}{{ trans_choice('ads.badges.years', $tenure['count'], ['count' => $tenure['count']]) }}</p>
        @endif
        {{-- Bottom line (PR 103, owner: "condition at the very bottom, like a badge"): [New/Used] City · 3d. --}}
        {{-- flex-wrap-reverse: when the line is too narrow (long Somali words on small phones), the city and time
             move up a line and the badge stays at the very bottom-left. Nothing is cut. --}}
        <p class="mt-auto flex min-w-0 flex-wrap-reverse items-center gap-x-1.5 gap-y-1 pt-1 text-xs text-stone-500">
            @if ($details && in_array($ad->condition, ['new', 'used'], true))
                <span class="shrink-0 rounded-full px-1.5 py-px font-semibold ring-1 ring-inset {{ $ad->condition === 'new' ? 'bg-brand-50 text-brand-800 ring-brand-200' : 'bg-stone-100 text-stone-700 ring-stone-200' }}" data-card-condition>{{ __('ads.form.'.$ad->condition) }}</span>
            @endif
            <span class="min-w-0">{{ $ad->city?->label }} · {{ $ad->published_at?->diffForHumans(short: true) }}</span>
        </p>
    </div>
</a>
@if ($canSave)
    {{-- Hidden until card-save.js runs (without JavaScript, save from the ad page).
         Top corner of the photo; in the list view the photo is on the start side (w-40, sm:w-52). --}}
    <button type="button" hidden data-card-save="{{ $ad->id }}" aria-pressed="false"
            aria-label="{{ __('ads.saved.save_label') }}: {{ $ad->title }}" data-label-on="{{ __('ads.saved.unsave_label') }}: {{ $ad->title }}" data-label-off="{{ __('ads.saved.save_label') }}: {{ $ad->title }}"
            class="absolute end-2 top-2 z-20 group-data-[view=list]/results:end-auto group-data-[view=list]/results:start-[7.25rem] sm:group-data-[view=list]/results:start-[10.25rem] flex size-9 items-center justify-center rounded-full bg-white/95 text-stone-700 shadow-sm ring-1 ring-stone-200 hover:text-brand-700 aria-pressed:bg-brand-700 aria-pressed:text-white aria-pressed:ring-brand-700">
        {{ icon('bookmark', 'size-4') }}
    </button>
@endif
</div>
