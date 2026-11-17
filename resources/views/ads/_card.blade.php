{{-- One ad in a grid (home, search, similar ads). Lazy photo, AVIF/WebP. --}}
@php
    $cover = $ad->cover;
    $badges = feature('ads.card_badges');
    $photoCount = $badges ? (int) ($ad->ready_photos_count ?? 0) : 0;
    $popular = $badges && feature('ad_stats') && (int) $ad->views >= config('iibsom.popular_views');
@endphp
<a href="{{ $ad->url() }}" class="group flex flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-stone-200 hover:ring-brand-200 group-data-[view=list]/results:flex-row">
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
                <img src="{{ $cover->url('thumb') }}" alt="{{ $ad->title }}" loading="lazy" decoding="async"
                     width="400" height="300" class="size-full object-cover">
            </picture>
        @else
            <span class="flex size-full items-center justify-center text-stone-400">{{ icon($ad->category->icon ?? 'tag', 'size-10') }}</span>
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-0.5 p-2.5">
        <p class="font-bold text-brand-800">{{ $ad->priceLabel() ?: __('ads.page.negotiable') }}</p>
        <p class="line-clamp-2 text-sm leading-snug group-hover:underline">{{ $ad->title }}</p>
        <p class="mt-auto pt-1 text-xs text-stone-500">{{ $ad->city?->label }} · {{ $ad->published_at?->diffForHumans(short: true) }}</p>
    </div>
</a>
