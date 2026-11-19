@extends('layouts.app')

@section('title', __('ads.saved.title'))

@section('content')
    <section class="mx-auto max-w-6xl px-4 py-8">
        <h1 class="text-2xl font-bold">{{ __('ads.saved.title') }}</h1>

        @if ($ads->isEmpty())
            <x-empty-state class="mt-6" icon="bookmark" :title="__('ads.saved.empty')">
                <a href="{{ lroute('home') }}" class="btn-primary">{{ __('ads.browse.all_ads') }}</a>
            </x-empty-state>
        @else
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                @foreach ($ads as $ad)
                    <div class="relative {{ $ad->isLive() ? '' : 'opacity-60' }}">
                        @include('ads._card', ['ad' => $ad])
                        @php($was = $ad->pivot?->price_when_saved)
                        @if ($ad->isLive() && feature('favourites.price_drop') && $was !== null && $ad->price !== null && (int) $ad->price < (int) $was)
                            <p class="mt-1 flex items-center gap-1 rounded-lg bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-200">{{ icon('trending-down', 'size-4 shrink-0') }} {{ __('notify.price_drop', ['from' => '$'.number_format((int) $was), 'to' => '$'.number_format((int) $ad->price)]) }}</p>
                        @endif
                        @unless ($ad->isLive())
                            <span class="absolute left-2 top-2 rounded-full bg-stone-900/80 px-2 py-0.5 text-xs font-semibold text-white">{{ __('ads.saved.gone') }}</span>
                        @endunless
                    </div>
                @endforeach
            </div>
            <div class="mt-6">{{ $ads->links() }}</div>
        @endif
    </section>
@endsection
