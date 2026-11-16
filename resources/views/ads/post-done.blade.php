@extends('layouts.app')

@section('title', __('ads.done.title'))

@section('content')
    <section class="mx-auto max-w-md px-4 py-12 text-center" data-post-done>
        <span class="mx-auto flex size-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">{{ icon('shield-check', 'size-8') }}</span>
        <h1 class="mt-4 text-2xl font-bold">{{ __('ads.done.title') }}</h1>
        <p class="mt-2 font-medium">“{{ $ad->title }}”</p>
        <p class="mt-2 text-stone-600">{{ $ad->status === \App\Enums\AdStatus::Live ? __('ads.done.live') : __('ads.done.pending') }}</p>
        <div class="mt-6 flex flex-col gap-2">
            <a href="{{ lroute('post') }}" class="btn-sell py-3">{{ __('ads.done.post_another') }}</a>
            <a href="{{ lroute('home') }}" class="btn-primary justify-center py-3">{{ __('ads.done.home') }}</a>
        </div>
    </section>
@endsection
