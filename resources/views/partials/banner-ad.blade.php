{{-- One banner ad in a paid slot (switch "banner_ads"). The link counts the click first (/b/{id}). --}}
@if ($bannerAd)
    <aside class="relative {{ $class ?? '' }}" aria-label="{{ __('site.banner_ad') }}">
        <a href="{{ route('banner.click', ['banner' => $bannerAd->id]) }}" rel="sponsored noopener" class="block overflow-hidden rounded-xl ring-1 ring-stone-200">
            <img src="{{ $bannerAd->imageUrl('small') }}" srcset="{{ $bannerAd->imageUrl('small') }} 800w, {{ $bannerAd->imageUrl('wide') }} 1600w"
                 sizes="{{ $sizes ?? '(min-width: 1024px) 860px, 100vw' }}" alt="{{ translated($bannerAd->title) }}" loading="lazy" class="h-auto w-full">
        </a>
        <span class="absolute end-2 top-2 rounded bg-stone-900/70 px-1.5 py-0.5 text-[11px] font-semibold text-white">{{ __('site.banner_ad') }}</span>
    </aside>
@endif
