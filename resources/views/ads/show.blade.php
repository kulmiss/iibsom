@extends('layouts.app')

@php
    $photos = $ad->photos;
    $cover = $photos->first();
    $desc = \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish($ad->description), 155);
@endphp

@section('title', $ad->title.' · '.$ad->priceLabel().' · '.$ad->city->label)
@section('description', $desc)

@push('head')
    <meta property="og:type" content="product">
    <meta property="og:title" content="{{ $ad->title }} · {{ $ad->priceLabel() }}">
    <meta property="og:description" content="{{ $desc }}">
    <meta property="og:url" content="{{ $ad->url() }}">
    @if ($cover)
        <meta property="og:image" content="{{ $cover->url('large') }}">
        {{-- Preload exactly what the <picture> below will choose (AVIF when the browser supports it). --}}
        @if ($cover->url('medium', 'avif') !== $cover->url('medium'))
            <link rel="preload" as="image" type="image/avif" imagesrcset="{{ $cover->url('medium', 'avif') }} 900w, {{ $cover->url('large', 'avif') }} 1600w" imagesizes="(min-width: 1024px) 720px, 100vw" fetchpriority="high">
        @else
            <link rel="preload" as="image" imagesrcset="{{ $cover->url('medium') }} 900w, {{ $cover->url('large') }} 1600w" imagesizes="(min-width: 1024px) 720px, 100vw" fetchpriority="high">
        @endif
    @endif
    <meta name="twitter:card" content="summary_large_image">
    @unless ($ad->isLive())
        <meta name="robots" content="noindex">
    @endunless
    {{-- Structured data so Google can show price and photo --}}
    <script type="application/ld+json" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">{!! json_encode(array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $ad->title,
        'description' => $desc,
        'image' => $photos->map(fn ($p) => $p->url('large'))->values()->all() ?: null,
        'category' => $ad->category->label,
        'itemCondition' => $ad->condition ? 'https://schema.org/'.($ad->condition === 'new' ? 'NewCondition' : 'UsedCondition') : null,
        'offers' => $ad->price !== null ? [
            '@type' => 'Offer',
            'price' => $ad->price,
            'priceCurrency' => 'USD',
            'availability' => $ad->isLive() ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
            'url' => $ad->url(),
            'areaServed' => $ad->city->label,
        ] : null,
    ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endpush

@push('scripts')
    @vite('resources/js/ad-page.js')
    {{-- This page has its own script: the language switch loads the page fully (PR 84). --}}
    <meta name="page-script" content="ad-page">
@endpush

@feature('ads.phone_top_bar')
    @section('phone_bar')
        <a href="{{ lroute('browse.category', ['category' => $ad->category->slug]) }}" class="icon-btn shrink-0" aria-label="{{ __('ads.page.back_to', ['category' => $ad->category->label]) }}" data-phone-back>{{ icon('chevron-left', 'size-6 rtl:rotate-180') }}</a>
        <p class="min-w-0 flex-1 truncate font-semibold" aria-hidden="true">{{ $ad->category->label }}</p>
        {{-- Share needs JavaScript (the phone's share sheet, or copy the link). --}}
        <button type="button" class="icon-btn hidden shrink-0 [.js_&]:flex" data-share-sheet data-url="{{ $ad->url() }}" data-title="{{ $ad->title }}" data-done="{{ __('ads.page.copied') }}" aria-label="{{ __('ads.page.share') }}" title="{{ __('ads.page.share') }}">{{ icon('share-2', 'size-5') }}<span class="sr-only" role="status" data-share-done></span></button>
        @feature('favourites')
            @include('ads._save-button', ['compact' => true])
        @endfeature
        @include('partials.language-switcher')
    @endsection
@endfeature

@section('content')
    @php($phoneOk = $ad->isLive() && $ad->user->phone && ! $ad->user->isBanned())
    @php($bar = feature('ads.sticky_contact') && $ad->isLive() && auth()->id() !== $ad->user_id
        && (($phoneOk && (feature('contact.call') || feature('contact.whatsapp'))) || feature('chat')))
    <div class="mx-auto max-w-6xl px-4 py-4 {{ $bar ? 'pb-24 lg:pb-4' : '' }}" @if (feature('recommendations') && $ad->isLive()) data-track-cat="{{ $ad->category_id }}" data-track-ad="{{ $ad->id }}" @endif>
        <nav class="text-sm text-stone-500" aria-label="Breadcrumb">
            <a href="{{ lroute('home') }}" class="hover:underline">{{ __('site.nav.home') }}</a>
            @if ($ad->category->parent)
                › <a href="{{ lroute('browse.category', ['category' => $ad->category->parent->slug]) }}" class="hover:underline">{{ $ad->category->parent->label }}</a>
            @endif
            › <a href="{{ lroute('browse.category', ['category' => $ad->category->slug]) }}" class="hover:underline">{{ $ad->category->label }}</a>
        </nav>

        @unless ($ad->isLive())
            <p class="mt-3 rounded-lg bg-accent-400/20 px-4 py-3 text-sm">
                {{ $ad->status === \App\Enums\AdStatus::Sold ? __('ads.page.sold') : __('ads.page.not_live', ['status' => $ad->status->label()]) }}
                @if ($ad->rejection_reason) “{{ $ad->rejection_reason }}”@endif
            </p>
        @endunless

        <div class="mt-3 grid gap-5 lg:grid-cols-[1fr_22rem]"
             @if (feature('ad_stats') && $ad->isLive() && auth()->id() !== $ad->user_id) data-stats="{{ route('ad.event', ['ad' => $ad->id]) }}" data-stats-id="{{ $ad->id }}" @endif>
            <div class="min-w-0 space-y-5">
                {{-- Gallery: swipe on phones, tap to zoom --}}
                <section class="overflow-hidden rounded-xl bg-white ring-1 ring-stone-200" aria-label="{{ __('ads.form.photos') }}">
                    @if ($photos->isEmpty())
                        <div class="flex aspect-[4/3] flex-col items-center justify-center gap-2 bg-gradient-to-br from-brand-50 to-stone-100 text-brand-300">{{ icon($ad->category->icon, 'size-16') }}<span class="text-sm font-medium text-stone-500">{{ __('ads.page.no_photo') }}</span></div>
                    @else
                        <div class="flex snap-x snap-mandatory overflow-x-auto" data-gallery>
                            @foreach ($photos as $photo)
                                <button type="button" class="relative aspect-[4/3] w-full shrink-0 snap-center bg-stone-900" data-zoom="{{ $photo->url('large') }}" data-index="{{ $loop->index }}">{{-- name comes from the photo's alt text --}}
                                    <picture>
                                        @if ($avif = $photo->url('medium', 'avif'))
                                            <source type="image/avif" srcset="{{ $avif }} 900w, {{ $photo->url('large', 'avif') }} 1600w" sizes="(min-width: 1024px) 720px, 100vw">
                                        @endif
                                        <img src="{{ $photo->url('medium') }}" srcset="{{ $photo->url('medium') }} 900w, {{ $photo->url('large') }} 1600w" sizes="(min-width: 1024px) 720px, 100vw"
                                             alt="{{ $ad->title }} – {{ __('ads.page.photo', ['n' => $loop->iteration, 'total' => $photos->count()]) }}"
                                             class="size-full object-contain" @if ($loop->first) fetchpriority="high" @else loading="lazy" fetchpriority="low" decoding="async" @endif width="{{ $photo->width ?: 900 }}" height="{{ $photo->height ?: 675 }}">
                                    </picture>
                                    <span class="absolute bottom-2 right-2 rounded bg-black/60 px-2 py-0.5 text-xs text-white" aria-hidden="true">{{ $loop->iteration }}/{{ $photos->count() }}</span>
                                </button>
                            @endforeach
                        </div>
                        @if ($photos->count() > 1 && feature('ads.gallery_plus'))
                            {{-- Small pictures: jump to any photo. Plain links to the zoom image without JavaScript. --}}
                            <ul class="flex gap-2 overflow-x-auto p-2" data-thumbs aria-label="{{ __('ads.page.all_photos') }}">
                                @foreach ($photos as $photo)
                                    <li class="shrink-0">
                                        <a href="{{ $photo->url('large') }}" data-thumb="{{ $loop->index }}" @if ($loop->first) aria-current="true" @endif
                                           class="block size-16 overflow-hidden rounded-lg ring-2 ring-transparent aria-[current=true]:ring-brand-600 sm:size-20">
                                            <img src="{{ $photo->url('thumb') }}" alt="{{ __('ads.page.photo', ['n' => $loop->iteration, 'total' => $photos->count()]) }}" loading="lazy" fetchpriority="low" decoding="async" width="80" height="80" class="size-full object-cover">
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                </section>

                <section class="rounded-xl bg-white p-4 ring-1 ring-stone-200">
                    <div class="flex items-start justify-between gap-3">
                        <h1 class="text-xl font-bold sm:text-2xl">{{ $ad->title }}</h1>
                        @feature('favourites')
                            @include('ads._save-button')
                        @endfeature
                    </div>
                    <p class="mt-1 text-2xl font-extrabold text-brand-800">{{ $ad->priceLabel() ?: __('ads.page.negotiable') }}</p>
                    @if ($market)
                        {{-- Usual price of similar ads (PR 83): real prices only, at least 5 ads, this ad left out. --}}
                        <div @class(['mt-2 rounded-lg px-3 py-2 text-sm ring-1', 'bg-amber-50 ring-amber-200' => $market['place'] === 'far_below', 'bg-stone-50 ring-stone-200' => $market['place'] !== 'far_below']) data-market-price data-place="{{ $market['place'] }}">
                            <p class="flex items-start gap-1.5 text-stone-700">{{ icon('chart-no-axes-column', 'mt-0.5 size-4 shrink-0 text-brand-700') }}
                                <span>{{ __('ads.market.range', ['what' => $market['what'], 'low' => number_format($market['low']), 'high' => number_format($market['high'])]) }}
                                    <span class="text-stone-500">{{ trans_choice('ads.market.from', $market['count'], ['count' => $market['count']]) }}</span></span></p>
                            <p @class(['mt-1 font-medium', 'text-amber-800' => $market['place'] === 'far_below', 'text-stone-700' => $market['place'] !== 'far_below'])>{{ match ($market['place']) { 'far_below' => __('ads.market.far_below'), 'below' => __('ads.market.below'), 'above' => __('ads.market.above'), default => __('ads.market.within') } }}</p>
                        </div>
                    @endif
                    <p class="mt-1 flex flex-wrap gap-x-3 text-sm text-stone-600">
                        <span>{{ icon('map-pin', 'inline size-4') }} {{ $ad->city->label }}@if ($ad->area), {{ $ad->area->label }}@endif</span>
                        <span>{{ __('ads.page.posted', ['when' => ($ad->published_at ?? $ad->created_at)->diffForHumans()]) }}</span>
                        @if (feature('ads.public_views') && feature('ad_stats') && (int) $ad->views > 0)
                            <span>{{ icon('eye', 'inline size-4') }} {{ trans_choice('ads.page.views', (int) $ad->views, ['count' => number_format((int) $ad->views)]) }}</span>
                        @endif
                    </p>
                    @feature('diaspora')
                        @include('ads._diaspora')
                    @endfeature

                    @if ($fieldRows || $ad->condition)
                        <h2 class="mt-5 font-semibold">{{ __('ads.page.details') }}</h2>
                        {{-- Long lists: the first 6 show, "Show all details" opens the rest (PR 82, "ads.details_more").
                             Without JavaScript all of them show. --}}
                        @php($detailCount = ($ad->condition ? 1 : 0) + count($fieldRows))
                        @php($foldDetails = feature('ads.details_more') && $detailCount > 6)
                        <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:grid-cols-3" id="ad-details" data-details>
                            @if ($ad->condition)
                                <div class="relative min-h-9 ps-11"><dt class="text-stone-500"><span class="absolute start-0 top-0 flex size-9 items-center justify-center rounded-lg bg-brand-50 text-brand-700" aria-hidden="true">{{ icon('tag', 'size-4') }}</span>{{ __('ads.form.condition') }}</dt><dd class="font-medium break-words">{{ __('ads.form.'.$ad->condition) }}</dd></div>
                            @endif
                            @foreach ($fieldRows as $row)
                                @php($extra = $foldDetails && ($loop->index + ($ad->condition ? 1 : 0)) >= 6)
                                <div @class(['relative min-h-9 ps-11', '[.js_&]:hidden' => $extra]) @if ($extra) data-detail-extra @endif><dt class="text-stone-500"><span class="absolute start-0 top-0 flex size-9 items-center justify-center rounded-lg bg-brand-50 text-brand-700" aria-hidden="true">{{ icon($row['icon'], 'size-4') }}</span>{{ $row['label'] }}</dt><dd class="font-medium break-words">@if ($row['url'])<a href="{{ $row['url'] }}" class="text-brand-800 underline decoration-brand-300 underline-offset-2 hover:decoration-brand-700" data-field-link>{{ $row['value'] }}</a>@else{{ $row['value'] }}@endif</dd></div>
                            @endforeach
                        </dl>
                        @if ($foldDetails)
                            <button type="button" class="mt-2 hidden items-center gap-1 text-sm font-semibold text-brand-800 underline [.js_&]:inline-flex" aria-controls="ad-details" aria-expanded="false"
                                    data-details-more data-more="{{ __('ads.page.details_more', ['count' => $detailCount]) }}" data-less="{{ __('ads.page.details_less') }}">{{ __('ads.page.details_more', ['count' => $detailCount]) }}</button>
                        @endif
                    @endif

                    <h2 class="mt-5 font-semibold">{{ __('ads.page.description') }}</h2>
                    {{-- Plain text only: escaped, line breaks kept --}}
                    <p class="mt-1 whitespace-pre-line break-words text-stone-800" id="ad-text" @feature('ads.read_more') data-read-more @endfeature>{{ $ad->description }}</p>
                    @feature('ads.read_more')
                        <button type="button" class="mt-1 text-sm font-semibold text-brand-700 underline" aria-controls="ad-text" aria-expanded="false" data-read-more-button hidden
                                data-more="{{ __('ads.page.read_more') }}" data-less="{{ __('ads.page.read_less') }}">{{ __('ads.page.read_more') }}</button>
                    @endfeature
                </section>
            </div>

            <aside class="space-y-4">
                {{-- Seller + contact --}}
                <section id="contact" class="scroll-mt-20 rounded-xl bg-white p-4 ring-1 ring-stone-200">
                    <h2 class="sr-only">{{ __('ads.page.seller') }}</h2>
                    <div class="flex items-center gap-3">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-brand-700 text-lg font-bold text-white">{{ $ad->user->initial() }}</span>
                        <div class="min-w-0">
                            <p class="flex min-w-0 items-center gap-1.5 font-semibold">
                                <span class="truncate">{{ $ad->user->name }}</span>
                                @if ($ad->user->isVerified())
                                    @include('partials.verified-badge')
                                @endif
                            </p>
                            <p class="text-xs text-stone-500">{{ $ad->user->city?->label }} · {{ __('ads.page.member_since', ['date' => mdate($ad->user->created_at, 'M Y')]) }}</p>
                            @if (feature('sellers.page'))
                                <a href="{{ lroute('seller.show', ['user' => $ad->user_id]) }}" class="text-xs font-medium text-brand-700 hover:underline" data-seller-link>{{ trans_choice('ads.page.seller_ads', max(1, $sellerLiveAds), ['count' => $sellerLiveAds]) }} →</a>
                            @else
                                <p class="text-xs text-stone-500">{{ trans_choice('ads.page.seller_ads', max(1, $sellerLiveAds), ['count' => $sellerLiveAds]) }}</p>
                            @endif
                            @feature('sellers.badges')
                                @php($tenure = \App\Support\Users\SellerBadges::tenure($ad->user))
                                @php($fast = \App\Support\Users\SellerBadges::repliesFast($ad->user))
                                @if ($tenure || $fast)
                                    <ul class="mt-1.5 flex flex-wrap gap-1.5 text-xs font-medium" aria-label="{{ __('ads.page.seller') }}">
                                        @if ($tenure)
                                            <li class="flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 text-brand-800">{{ icon('clock', 'size-3.5') }} {{ trans_choice('ads.badges.'.$tenure['unit'], $tenure['count'], ['count' => $tenure['count']]) }}</li>
                                        @endif
                                        @if ($fast)
                                            <li class="flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-800" title="{{ __('ads.badges.fast_hint') }}">{{ icon('zap', 'size-3.5') }} {{ __('ads.badges.fast') }}</li>
                                        @endif
                                    </ul>
                                @endif
                            @endfeature
                            @include('partials.seller-rating', ['seller' => $ad->user])
                            @if (feature('sellers.page'))
                                <div class="mt-2">@include('sellers._follow-button', ['seller' => $ad->user, 'following' => $following])</div>
                            @endif
                            @if (feature('shops') && $ad->user->shop?->isActive())
                                <a href="{{ $ad->user->shop->url() }}" class="mt-1 inline-flex items-center gap-1 text-sm font-semibold text-brand-700 hover:underline">{{ icon('store', 'size-4') }} {{ __('account.shop.visit') }}</a>
                            @endif
                        </div>
                    </div>

                    @if ($ad->isLive() && $ad->user->phone && (feature('contact.call') || feature('contact.whatsapp')))
                        <div class="mt-4 space-y-2" data-contact data-url="{{ lroute('ad.contact', ['ad' => $ad->id] + (auth()->id() === $ad->user_id ? ['mine' => 1] : [])) }}">
                            <button type="button" class="btn-sell w-full py-3 text-base normal-case" data-reveal>
                                {{ icon('user', 'size-5') }} <span class="ms-1">{{ __('ads.page.show_number') }}</span>
                            </button>
                            <p class="hidden text-center text-lg font-bold tracking-wide" data-number></p>
                            @feature('contact.safety_tip')
                                <p class="hidden rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-amber-200" data-safety-tip role="note">
                                    {{ icon('shield-check', 'size-4 me-1 inline-block align-[-3px]') }}{{ __('ads.page.contact_tip') }}
                                    @feature('site.safety_box') <a href="{{ lroute('page', ['slug' => 'safety']) }}" class="font-medium underline">{{ __('ads.page.contact_tip_more') }}</a>@endfeature
                                </p>
                            @endfeature
                            <div class="hidden grid-cols-2 gap-2" data-buttons>
                                @feature('contact.whatsapp')
                                    <a href="#" data-whatsapp class="hidden items-center justify-center gap-2 rounded-lg bg-[#1f9d55] px-3 py-3 font-semibold text-white hover:bg-[#188046]" target="_blank" rel="noopener">{{ __('ads.page.whatsapp') }}</a>
                                @endfeature
                                @feature('contact.call')
                                    <a href="#" data-call class="hidden items-center justify-center gap-2 rounded-lg bg-brand-700 px-3 py-3 font-semibold text-white hover:bg-brand-800">{{ __('ads.page.call') }}</a>
                                @endfeature
                            </div>
                        </div>
                    @endif
                    @if ($ad->isLive() && feature('notifications') && feature('contact.call_back'))
                        {{-- "Ask the seller to call me" (PR 66): the buyer sends their own name and number to this seller. --}}
                        @auth
                            @if (auth()->id() !== $ad->user_id && auth()->user()->phone)
                                <form method="post" action="{{ lroute('call_back', ['ad' => $ad->id]) }}" class="mt-2" data-call-back>
                                    @csrf
                                    <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg px-3 py-3 font-semibold text-brand-800 ring-1 ring-brand-200 hover:bg-brand-50">{{ icon('phone-call', 'size-5') }} {{ __('notify.call_back.button') }}</button>
                                    <p class="mt-1 text-center text-xs text-stone-500">{{ __('notify.call_back.note', ['phone' => \App\Support\Phone\SomaliPhone::format(auth()->user()->phone)]) }}</p>
                                </form>
                            @endif
                        @else
                            @if (sign_in_open())
                                <a href="{{ lroute('login') }}" class="mt-2 flex w-full items-center justify-center gap-2 rounded-lg px-3 py-3 font-semibold text-brand-800 ring-1 ring-brand-200 hover:bg-brand-50" title="{{ __('notify.call_back.sign_in') }}">{{ icon('phone-call', 'size-5') }} {{ __('notify.call_back.button') }}</a>
                            @endif
                        @endauth
                    @endif
                    @feature('chat')
                        @if ($ad->isLive() && auth()->id() !== $ad->user_id)
                            @auth
                                <form method="post" action="{{ lroute('chat.start', ['ad' => $ad->id]) }}" class="mt-2">
                                    @csrf
                                    @feature('chat.quick_replies')
                                        <p class="mb-1.5 text-xs font-medium text-stone-500">{{ __('chat.quick.title') }}</p>
                                        <div class="mb-2 flex flex-wrap gap-1.5">
                                            @foreach (\App\Http\Controllers\Account\ChatController::QUICK as $q)
                                                <button type="submit" name="quick" value="{{ $q }}" class="rounded-full bg-brand-50 px-3 py-1.5 text-sm font-medium text-brand-800 ring-1 ring-brand-200 hover:bg-brand-200">{{ __('chat.quick.'.$q) }}</button>
                                            @endforeach
                                        </div>
                                    @endfeature
                                    <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg px-3 py-3 font-semibold text-brand-800 ring-1 ring-brand-200 hover:bg-brand-50">{{ icon('message-circle', 'size-5') }} {{ __('chat.start') }}</button>
                                </form>
                                @feature('chat.ad_box')
                                    {{-- Write to the seller right here (PR 85): sent with the same checks as the chat, then the chat opens. --}}
                                    <form id="message" method="post" action="{{ lroute('chat.start', ['ad' => $ad->id]) }}" class="mt-3 scroll-mt-20" data-ad-message>
                                        @csrf
                                        <label for="ad-message" class="text-sm font-medium">{{ __('chat.ad_box.label', ['name' => $ad->user->name]) }}</label>
                                        <textarea id="ad-message" name="message" rows="3" required maxlength="{{ \App\Models\Message::MAX_LENGTH }}"
                                                  class="form-input mt-1" placeholder="{{ __('chat.ad_box.placeholder') }}"
                                                  @if ($errors->has('message')) aria-invalid="true" aria-describedby="ad-message-error" @endif>{{ old('message') }}</textarea>
                                        @error('message')<p id="ad-message-error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                                        <button type="submit" class="btn-primary mt-2 w-full justify-center">{{ icon('send', 'size-4') }} {{ __('chat.ad_box.send') }}</button>
                                    </form>
                                @endfeature
                                @feature('chat.offers')
                                    {{-- Make an offer: the chat opens with the offer written in; the buyer still taps Send. --}}
                                    <details id="offer-box" class="mt-2 scroll-mt-20 rounded-lg ring-1 ring-brand-200" data-offer @if ($errors->has('offer')) open @endif>
                                        <summary class="flex cursor-pointer list-none items-center justify-center gap-2 px-3 py-3 font-semibold text-brand-800 hover:bg-brand-50 [&::-webkit-details-marker]:hidden">{{ icon('hand-coins', 'size-5') }} {{ __('chat.offer.button') }}</summary>
                                        <form method="post" action="{{ lroute('chat.start', ['ad' => $ad->id]) }}" class="border-t border-brand-100 p-3">
                                            @csrf
                                            <label for="offer" class="text-sm font-medium">{{ __('chat.offer.label') }}</label>
                                            <div class="mt-1 flex gap-2">
                                                <input id="offer" name="offer" type="number" inputmode="numeric" min="1" max="99999999" step="1" required value="{{ old('offer') }}"
                                                       class="form-input min-w-0 flex-1" @if ($errors->has('offer')) aria-invalid="true" aria-describedby="offer-error" @endif>
                                                <button type="submit" class="btn-primary shrink-0">{{ __('chat.offer.continue') }}</button>
                                            </div>
                                            @error('offer')<p id="offer-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                                            <p class="mt-1.5 text-xs text-stone-500">{{ __('chat.offer.note') }}</p>
                                        </form>
                                    </details>
                                @endfeature
                            @else
                                @if (sign_in_open())
                                    <a href="{{ lroute('login') }}" class="mt-2 flex w-full items-center justify-center gap-2 rounded-lg px-3 py-3 font-semibold text-brand-800 ring-1 ring-brand-200 hover:bg-brand-50" title="{{ __('chat.sign_in') }}">{{ icon('message-circle', 'size-5') }} {{ __('chat.start') }}</a>
                                @endif
                            @endauth
                        @endif
                    @endfeature
                </section>

                @include('partials.banner-ad', ['bannerAd' => \App\Models\Banner::pick('ad', $ad->category, $ad->city_id), 'sizes' => '(min-width: 1024px) 352px, 100vw'])

                @feature('share.buttons')
                    <section class="flex flex-wrap items-center gap-2 rounded-xl bg-white p-4 ring-1 ring-stone-200" data-share data-url="{{ $ad->url() }}" data-title="{{ $ad->title }}">
                        <span class="text-sm font-semibold">{{ __('ads.page.share') }}:</span>
                        <a class="chip bg-stone-50 ring-stone-200" target="_blank" rel="noopener" href="https://wa.me/?text={{ rawurlencode($ad->title.' '.$ad->url()) }}">{{ __('ads.page.share_whatsapp') }}</a>
                        <a class="chip bg-stone-50 ring-stone-200" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($ad->url()) }}">{{ __('ads.page.share_facebook') }}</a>
                        <button type="button" class="chip bg-stone-50 ring-stone-200" data-copy data-done="{{ __('ads.page.copied') }}">{{ __('ads.page.copy_link') }}</button>
                    </section>
                @endfeature

                @feature('site.safety_box')
                    <section class="rounded-xl bg-brand-50 p-4 ring-1 ring-brand-200">
                        <h2 class="flex items-center gap-2 font-semibold">{{ icon('shield-check', 'size-5 text-brand-700') }} {{ __('ads.page.safety_title') }}</h2>
                        <ul class="mt-2 list-disc space-y-1 ps-5 text-sm">
                            <li>{{ __('ads.page.safety_1') }}</li>
                            <li>{{ __('ads.page.safety_2') }}</li>
                            <li>{{ __('ads.page.safety_3') }}</li>
                        </ul>
                        <a href="{{ lroute('page', ['slug' => 'safety']) }}" class="mt-2 inline-block text-sm font-medium text-brand-800 underline">{{ __('ads.page.safety_more') }}</a>
                    </section>
                @endfeature

                @if (feature('ads.reports') && $ad->isLive() && auth()->id() !== $ad->user_id)
                    <section id="report" class="rounded-xl bg-white p-4 ring-1 ring-stone-200">
                        @if ($reported)
                            <p class="text-sm text-stone-600">{{ __('ads.page.reported') }}</p>
                        @else
                            @if (feature('ads.mark_unavailable') && (auth()->check() || feature('ads.guest_reports')))
                                {{-- One tap: opens the report box with "Already sold" chosen (PR 82). Without JavaScript it
                                     only jumps to the box. Same report, same checks, nothing new is stored. --}}
                                <a href="#report" class="mb-3 flex items-center gap-2 text-sm font-medium text-stone-700 underline" data-mark-sold>{{ icon('circle-check', 'size-4 text-stone-500') }} {{ __('ads.page.mark_sold') }}</a>
                            @endif
                        @endif
                        @if ($reported)
                        @elseif (auth()->check())
                            <details>
                                <summary class="cursor-pointer text-sm font-medium text-red-700">{{ __('ads.page.report') }}</summary>
                                <form method="post" action="{{ lroute('ad.report', ['ad' => $ad->id]) }}" class="mt-3 space-y-3">
                                    @csrf
                                    <fieldset>
                                        <legend class="form-label">{{ __('ads.page.report_reason') }}</legend>
                                        @foreach (\App\Models\Report::reasons() as $reason)
                                            <label class="flex items-center gap-2 py-0.5 text-sm"><input type="radio" name="reason" value="{{ $reason }}" required class="size-4"> {{ __('reports.reason.'.$reason) }}</label>
                                        @endforeach
                                    </fieldset>
                                    <textarea name="details" rows="2" maxlength="500" class="form-input" aria-label="{{ __('ads.page.report_details') }}" placeholder="{{ __('ads.page.report_details') }}"></textarea>
                                    {{-- Honeypot: hidden from people, bots fill it --}}
                                    <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
                                    <button class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white">{{ __('ads.page.report_send') }}</button>
                                </form>
                            </details>
                        @elseif (feature('ads.guest_reports'))
                            {{-- Not signed in: report without an account (no cookie; the answer comes back in the address). --}}
                            @php($guestState = in_array(request()->query('report'), ['thanks', 'error', 'check'], true) ? request()->query('report') : null)
                            @if ($guestState === 'thanks')
                                <p class="text-sm text-green-800" role="status">{{ __('ads.page.report_thanks') }}</p>
                            @else
                                @php($reportToken = \App\Support\Safety\GuestReports::token())
                                <details @if ($guestState) open @endif>
                                    <summary class="cursor-pointer text-sm font-medium text-red-700">{{ __('ads.page.report') }}</summary>
                                    @if ($guestState === 'error')
                                        <p class="form-error mt-2" role="alert">{{ __('ads.page.report_guest_error') }}</p>
                                    @endif
                                    <form method="post" action="{{ lroute('ad.report_guest', ['ad' => $ad->id]) }}" class="mt-3 space-y-3" data-guest-report="{{ lroute('ad.report_token') }}">
                                        <fieldset>
                                            <legend class="form-label">{{ __('ads.page.report_reason') }}</legend>
                                            @foreach (\App\Models\Report::reasons() as $reason)
                                                <label class="flex items-center gap-2 py-0.5 text-sm"><input type="radio" name="reason" value="{{ $reason }}" required class="size-4"> {{ __('reports.reason.'.$reason) }}</label>
                                            @endforeach
                                        </fieldset>
                                        <textarea name="details" rows="2" maxlength="500" class="form-input" aria-label="{{ __('ads.page.report_details') }}" placeholder="{{ __('ads.page.report_details') }}"></textarea>
                                        <div>
                                            <label for="report-contact" class="form-label">{{ __('ads.page.report_contact') }}</label>
                                            <input id="report-contact" name="contact" type="text" maxlength="120" autocomplete="tel" class="form-input" aria-describedby="report-contact-help">
                                            <p id="report-contact-help" class="form-help">{{ __('ads.page.report_contact_help') }}</p>
                                        </div>
                                        @if ($guestState === 'check' || \App\Support\Safety\GuestReports::underAttack())
                                            @php([$sumA, $sumB] = \App\Support\Safety\GuestReports::sum($reportToken))
                                            <div>
                                                <label for="report-sum" class="form-label">{{ __('ads.page.report_sum', ['a' => $sumA, 'b' => $sumB]) }}</label>
                                                <input id="report-sum" name="sum" type="text" inputmode="numeric" maxlength="2" required class="form-input w-20">
                                            </div>
                                        @endif
                                        <input type="hidden" name="t" value="{{ $reportToken }}">
                                        {{-- Honeypot: hidden from people, bots fill it --}}
                                        <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
                                        <button class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white">{{ __('ads.page.report_send') }}</button>
                                        <p class="text-xs text-stone-500">{{ __('ads.page.report_guest_privacy') }}</p>
                                    </form>
                                </details>
                            @endif
                        @elseif (sign_in_open())
                            <a href="{{ lroute('login') }}" class="text-sm font-medium text-red-700 underline">{{ __('ads.page.report_sign_in') }}</a>
                        @endif
                    </section>
                @endif
            </aside>
        </div>

        @if (feature('ads.post_like_this') && feature('ads.posting') && $ad->isLive() && auth()->id() !== $ad->user_id)
            {{-- "Have one like this?" (PR 82): opens Post an ad in this category with the same brand, model and condition. --}}
            <aside class="mt-8 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-brand-50 p-4 ring-1 ring-brand-200" data-post-like-this>
                <p class="flex items-center gap-2 font-medium text-stone-800">{{ icon('square-plus', 'size-5 text-brand-700') }} {{ __('ads.page.like_this_text') }}</p>
                <a href="{{ lroute('post.form', ['category' => $ad->category->slug, 'like' => $ad->id]) }}" class="btn-sell" rel="nofollow">{{ __('ads.page.like_this_button') }}</a>
            </aside>
        @endif

        @if ($discover !== [])
            {{-- "Discover more" (PR 84): searches made from this ad's details; each one has at least this ad. --}}
            <nav class="mt-8" aria-labelledby="discover-title" data-discover>
                <h2 id="discover-title" class="text-lg font-semibold">{{ __('ads.discover.title') }}</h2>
                <ul class="mt-3 flex flex-wrap gap-2">
                    @foreach ($discover as $link)
                        <li><a href="{{ $link['url'] }}" class="chip gap-1.5 bg-white ring-stone-200 hover:bg-brand-50">{{ icon('search', 'size-4 text-brand-700') }}{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif

        @if ($similar->isNotEmpty())
            <section class="mt-8">
                <h2 class="text-lg font-semibold">{{ __('ads.page.similar') }}</h2>
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    @foreach ($similar as $s)
                        @include('ads._card', ['ad' => $s])
                    @endforeach
                </div>
            </section>
        @endif

        @if ($fromSeller->isNotEmpty())
            <section class="mt-8" data-more-from-seller>
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-lg font-semibold">{{ __('ads.page.more_from_seller', ['name' => $ad->user->name]) }}</h2>
                    @if (feature('sellers.page') && $sellerLiveAds > $fromSeller->count() + 1)
                        <a href="{{ lroute('seller.show', ['user' => $ad->user_id]) }}" class="text-sm font-medium text-brand-700 underline">{{ trans_choice('ads.page.seller_ads', $sellerLiveAds, ['count' => $sellerLiveAds]) }} →</a>
                    @endif
                </div>
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    @foreach ($fromSeller as $s)
                        @include('ads._card', ['ad' => $s])
                    @endforeach
                </div>
            </section>
        @endif

        @if (feature('ads.recently_viewed') && feature('recommendations'))
            {{-- Filled by for-you.js from this browser's own history (this ad left out). --}}
            <div data-for-you data-url="{{ lroute('for-you') }}" data-exclude="{{ $ad->id }}" hidden class="mt-8">
                <section data-slot="recent" hidden>
                    <h2 class="text-lg font-semibold">{{ __('site.home.recently_viewed') }}</h2>
                    <div data-cards class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6"></div>
                </section>
            </div>
        @endif
    </div>

    @if ($bar)
        {{-- Phones: price and contact stay in reach while reading (PR 76). Without JavaScript the buttons jump to the
             contact box; with it, Call and WhatsApp show the number first (counted like the normal button). --}}
        <div class="fixed inset-x-0 bottom-[calc(3.6rem+env(safe-area-inset-bottom))] z-20 border-t border-stone-200 bg-white/95 px-3 py-2 backdrop-blur lg:hidden" data-contact-bar>
            <div class="mx-auto flex max-w-md items-center gap-2">
                <p class="min-w-0 flex-1 truncate font-extrabold text-brand-800">{{ $ad->priceLabel() ?: __('ads.page.negotiable') }}</p>
                @if ($phoneOk && feature('contact.call'))
                    <a href="#contact" class="flex items-center gap-1 rounded-lg bg-brand-700 px-3 py-2 text-sm font-semibold text-white" data-bar="call">{{ icon('phone', 'size-4') }}<span class="max-[400px]:sr-only">{{ __('ads.page.call') }}</span></a>
                @endif
                @if ($phoneOk && feature('contact.whatsapp'))
                    <a href="#contact" class="flex items-center gap-1 rounded-lg bg-[#15803d] px-3 py-2 text-sm font-semibold text-white" data-bar="whatsapp">{{ icon('social-whatsapp', 'size-4') }}<span class="max-[400px]:sr-only">{{ __('ads.page.whatsapp') }}</span></a>
                @endif
                @feature('chat')
                    <a href="#contact" class="flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-semibold text-brand-800 ring-1 ring-brand-200" data-bar="chat">{{ icon('message-circle', 'size-4') }}<span class="max-[400px]:sr-only">{{ __('ads.page.bar_chat') }}</span></a>
                    @if (feature('chat.offers') && auth()->check())
                        <a href="#offer-box" class="flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-semibold text-brand-800 ring-1 ring-brand-200" data-bar="offer">{{ icon('hand-coins', 'size-4') }}<span class="max-[400px]:sr-only">{{ __('ads.page.bar_offer') }}</span></a>
                    @endif
                @endfeature
            </div>
        </div>
    @endif

    @if (feature('ads.gallery_plus') && $photos->isNotEmpty())
        {{-- Full screen: all photos, swipe or arrow keys, pinch to zoom (PR 76). --}}
        <dialog data-viewer aria-label="{{ __('ads.page.all_photos') }}" class="m-0 h-dvh max-h-none w-screen max-w-none bg-black p-0 text-white backdrop:bg-black">
            <div class="absolute inset-x-0 top-0 z-10 flex items-center justify-between gap-3 bg-gradient-to-b from-black/70 to-transparent p-3">
                <p class="text-sm font-semibold" aria-live="polite" data-viewer-count></p>
                <form method="dialog"><button class="flex size-10 items-center justify-center rounded-full bg-white/15 hover:bg-white/25" aria-label="{{ __('ads.page.close') }}">{{ icon('x', 'size-5') }}</button></form>
            </div>
            <div class="flex h-full snap-x snap-mandatory overflow-x-auto overscroll-contain" data-viewer-track>
                @foreach ($photos as $photo)
                    <figure class="flex h-full w-full shrink-0 snap-center items-center justify-center">
                        <img data-src="{{ $photo->url('large') }}" alt="{{ $ad->title }} – {{ __('ads.page.photo', ['n' => $loop->iteration, 'total' => $photos->count()]) }}"
                             class="max-h-full max-w-full touch-pinch-zoom object-contain" decoding="async">
                    </figure>
                @endforeach
            </div>
            @if ($photos->count() > 1)
                <button type="button" class="absolute start-3 top-1/2 hidden size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/15 hover:bg-white/25 sm:flex rtl:rotate-180" data-viewer-prev aria-label="{{ __('ads.page.photo_prev') }}">{{ icon('chevron-left', 'size-6') }}</button>
                <button type="button" class="absolute end-3 top-1/2 hidden size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/15 hover:bg-white/25 sm:flex rtl:rotate-180" data-viewer-next aria-label="{{ __('ads.page.photo_next') }}">{{ icon('chevron-right', 'size-6') }}</button>
            @endif
        </dialog>
    @else
        <dialog data-zoom-dialog class="m-auto max-h-none max-w-none bg-transparent p-0 backdrop:bg-black/90">
            <form method="dialog"><button class="fixed right-3 top-3 z-10 rounded-full bg-white/90 px-3 py-1 text-lg font-bold" aria-label="Close">✕</button></form>
            <img alt="" data-zoom-img class="max-h-[95vh] max-w-[95vw] touch-pinch-zoom object-contain">
        </dialog>
    @endif
@endsection
