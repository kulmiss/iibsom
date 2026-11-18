@extends('layouts.app')

@section('title', __('ads.done.title'))

@section('content')
    <section class="mx-auto max-w-md px-4 py-8 text-center" data-post-done>
        <div class="text-start">@include('ads._post-steps', ['current' => 3])</div>
        <span class="mx-auto flex size-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">{{ icon('shield-check', 'size-8') }}</span>
        <h1 class="mt-4 text-2xl font-bold">{{ __('ads.done.title') }}</h1>
        <p class="mt-2 font-medium">“{{ $ad->title }}”</p>
        <p class="mt-2 text-stone-600">{{ $ad->status === \App\Enums\AdStatus::Live ? __('ads.done.live') : __('ads.done.pending') }}</p>
        @feature('post.done_plus')
            @php($isLive = $ad->status === \App\Enums\AdStatus::Live)
            @if ($isLive)
                <a href="{{ $ad->url() }}" class="mt-4 inline-block font-medium text-brand-700 underline" data-done-view>{{ __('ads.done.view_ad') }}</a>
            @endif
            <div class="mt-6 rounded-2xl bg-white p-4 text-start shadow-sm ring-1 ring-stone-200" data-done-share>
                <h2 class="flex items-center gap-2 font-semibold">{{ icon('send', 'size-5 text-brand-700') }} {{ __('ads.done.share_title') }}</h2>
                @if ($isLive)
                    <p class="mt-1 text-sm text-stone-600">{{ __('ads.done.share_text') }}</p>
                    <div class="mt-3 flex flex-wrap gap-2" data-share data-url="{{ $ad->url() }}" data-title="{{ $ad->title }}">
                        <a class="btn-chip" target="_blank" rel="noopener" href="https://wa.me/?text={{ rawurlencode($ad->title.' '.$ad->url()) }}">{{ icon('social-whatsapp', 'size-4') }} {{ __('ads.done.whatsapp') }}</a>
                        <a class="btn-chip" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($ad->url()) }}">{{ icon('social-facebook', 'size-4') }} {{ __('ads.done.facebook') }}</a>
                        <button type="button" class="btn-chip" data-copy-link data-done="{{ __('ads.done.copied') }}">{{ __('ads.done.copy_link') }}</button>
                    </div>
                @else
                    <p class="mt-1 text-sm text-stone-600">{{ __('ads.done.share_later') }}</p>
                @endif
            </div>
            @feature('ads.free_bump')
                <div class="mt-3 rounded-2xl bg-brand-50 p-4 text-start ring-1 ring-brand-200" data-done-bump>
                    <h2 class="flex items-center gap-2 font-semibold">{{ icon('arrow-up-circle', 'size-5 text-brand-700') }} {{ __('ads.done.bump_title') }}</h2>
                    <p class="mt-1 text-sm text-stone-700">{{ __('ads.done.bump_text') }}</p>
                    <a href="{{ lroute('account.ads') }}" class="mt-2 inline-block text-sm font-medium text-brand-700 underline">{{ __('ads.done.my_ads') }}</a>
                </div>
            @endfeature
        @endfeature
        <div class="mt-6 flex flex-col gap-2">
            <a href="{{ lroute('post') }}" class="btn-sell py-3">{{ __('ads.done.post_another') }}</a>
            <a href="{{ lroute('home') }}" class="btn-primary justify-center py-3">{{ __('ads.done.home') }}</a>
        </div>
    </section>
@endsection
