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
@endpush

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-4" @if (feature('recommendations') && $ad->isLive()) data-track-cat="{{ $ad->category_id }}" data-track-ad="{{ $ad->id }}" @endif>
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
                        <div class="flex aspect-[4/3] items-center justify-center bg-stone-100 text-stone-400">{{ icon($ad->category->icon, 'size-16') }}<span class="sr-only">{{ __('ads.page.no_photo') }}</span></div>
                    @else
                        <div class="flex snap-x snap-mandatory overflow-x-auto" data-gallery>
                            @foreach ($photos as $photo)
                                <button type="button" class="relative aspect-[4/3] w-full shrink-0 snap-center bg-stone-900" data-zoom="{{ $photo->url('large') }}">{{-- name comes from the photo's alt text --}}
                                    <picture>
                                        @if ($avif = $photo->url('medium', 'avif'))
                                            <source type="image/avif" srcset="{{ $avif }} 900w, {{ $photo->url('large', 'avif') }} 1600w" sizes="(min-width: 1024px) 720px, 100vw">
                                        @endif
                                        <img src="{{ $photo->url('medium') }}" srcset="{{ $photo->url('medium') }} 900w, {{ $photo->url('large') }} 1600w" sizes="(min-width: 1024px) 720px, 100vw"
                                             alt="{{ $ad->title }} – {{ __('ads.page.photo', ['n' => $loop->iteration, 'total' => $photos->count()]) }}"
                                             class="size-full object-contain" @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif decoding="async" width="{{ $photo->width ?: 900 }}" height="{{ $photo->height ?: 675 }}">
                                    </picture>
                                    <span class="absolute bottom-2 right-2 rounded bg-black/60 px-2 py-0.5 text-xs text-white" aria-hidden="true">{{ $loop->iteration }}/{{ $photos->count() }}</span>
                                </button>
                            @endforeach
                        </div>
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
                    <p class="mt-1 flex flex-wrap gap-x-3 text-sm text-stone-600">
                        <span>{{ icon('map-pin', 'inline size-4') }} {{ $ad->city->label }}@if ($ad->area), {{ $ad->area->label }}@endif</span>
                        <span>{{ __('ads.page.posted', ['when' => ($ad->published_at ?? $ad->created_at)->diffForHumans()]) }}</span>
                    </p>
                    @feature('diaspora')
                        @include('ads._diaspora')
                    @endfeature

                    @if ($fieldRows || $ad->condition)
                        <h2 class="mt-5 font-semibold">{{ __('ads.page.details') }}</h2>
                        <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-3">
                            @if ($ad->condition)
                                <div><dt class="text-stone-500">{{ __('ads.form.condition') }}</dt><dd class="font-medium">{{ __('ads.form.'.$ad->condition) }}</dd></div>
                            @endif
                            @foreach ($fieldRows as $row)
                                <div><dt class="text-stone-500">{{ $row['label'] }}</dt><dd class="font-medium">{{ $row['value'] }}</dd></div>
                            @endforeach
                        </dl>
                    @endif

                    <h2 class="mt-5 font-semibold">{{ __('ads.page.description') }}</h2>
                    {{-- Plain text only: escaped, line breaks kept --}}
                    <p class="mt-1 whitespace-pre-line break-words text-stone-800">{{ $ad->description }}</p>
                </section>
            </div>

            <aside class="space-y-4">
                {{-- Seller + contact --}}
                <section class="rounded-xl bg-white p-4 ring-1 ring-stone-200">
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
                            <p class="text-xs text-stone-500">{{ trans_choice('ads.page.seller_ads', max(1, $sellerLiveAds), ['count' => $sellerLiveAds]) }}</p>
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
                            @else
                                @feature('login.google')
                                    <a href="{{ lroute('login') }}" class="mt-2 flex w-full items-center justify-center gap-2 rounded-lg px-3 py-3 font-semibold text-brand-800 ring-1 ring-brand-200 hover:bg-brand-50" title="{{ __('chat.sign_in') }}">{{ icon('message-circle', 'size-5') }} {{ __('chat.start') }}</a>
                                @endfeature
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
                        @elseif (auth()->check())
                            <details>
                                <summary class="cursor-pointer text-sm font-medium text-red-700">{{ __('ads.page.report') }}</summary>
                                <form method="post" action="{{ lroute('ad.report', ['ad' => $ad->id]) }}" class="mt-3 space-y-3">
                                    @csrf
                                    <fieldset>
                                        <legend class="form-label">{{ __('ads.page.report_reason') }}</legend>
                                        @foreach (\App\Models\Report::REASONS as $reason)
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
                                    <form method="post" action="{{ lroute('ad.report_guest', ['ad' => $ad->id]) }}" class="mt-3 space-y-3">
                                        <fieldset>
                                            <legend class="form-label">{{ __('ads.page.report_reason') }}</legend>
                                            @foreach (\App\Models\Report::REASONS as $reason)
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
                        @elseif (feature('login.google'))
                            <a href="{{ lroute('login') }}" class="text-sm font-medium text-red-700 underline">{{ __('ads.page.report_sign_in') }}</a>
                        @endif
                    </section>
                @endif
            </aside>
        </div>

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
    </div>

    <dialog data-zoom-dialog class="m-auto max-h-none max-w-none bg-transparent p-0 backdrop:bg-black/90">
        <form method="dialog"><button class="fixed right-3 top-3 z-10 rounded-full bg-white/90 px-3 py-1 text-lg font-bold" aria-label="Close">✕</button></form>
        <img alt="" data-zoom-img class="max-h-[95vh] max-w-[95vw] touch-pinch-zoom object-contain">
    </dialog>
@endsection
