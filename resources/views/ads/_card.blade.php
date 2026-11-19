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
<div class="relative" data-card>
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
        @if ($details && in_array($ad->condition, ['new', 'used'], true))
            <p class="text-xs font-medium text-stone-600">{{ __('ads.form.'.$ad->condition) }}</p>
        @endif
        @if ($details && filled($ad->description))
            {{-- List view only: the first words of the description. --}}
            <div class="hidden group-data-[view=list]/results:block"><p class="line-clamp-2 text-sm text-stone-600">{{ \Illuminate\Support\Str::limit(preg_replace('/\s+/u', ' ', (string) $ad->description) ?? '', 160) }}</p></div>
        @endif
        <p class="mt-auto pt-1 text-xs text-stone-500">{{ $ad->city?->label }} · {{ $ad->published_at?->diffForHumans(short: true) }}</p>
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
