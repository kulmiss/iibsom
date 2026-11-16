{{-- One ad in a grid (home, search, similar ads). Lazy photo, AVIF/WebP. --}}
@php($cover = $ad->cover)
<a href="{{ $ad->url() }}" class="group flex flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-stone-200 hover:ring-brand-200">
    <div class="relative aspect-[4/3] bg-stone-100">
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
