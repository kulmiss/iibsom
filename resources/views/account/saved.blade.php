@extends('layouts.app')

@section('title', __('ads.saved.title'))

@section('content')
    <section class="mx-auto max-w-6xl px-4 py-8">
        <h1 class="text-2xl font-bold">{{ __('ads.saved.title') }}</h1>

        @if ($ads->isEmpty())
            <div class="mt-6 rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center text-stone-600">
                {{ icon('bookmark', 'mx-auto size-8 text-stone-400') }}
                <p class="mt-2">{{ __('ads.saved.empty') }}</p>
            </div>
        @else
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                @foreach ($ads as $ad)
                    <div class="relative {{ $ad->isLive() ? '' : 'opacity-60' }}">
                        @include('ads._card', ['ad' => $ad])
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
