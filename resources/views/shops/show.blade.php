@extends('layouts.app')

@section('title', $shop->name)
@section('description', \Illuminate\Support\Str::limit($shop->description ?: $shop->name.' · iibsom', 155))

@push('head')
    @if ($ads->total() === 0 || $ads->currentPage() > 1)
        <meta name="robots" content="noindex, follow">
    @endif
@endpush

@section('content')
    <section class="mx-auto max-w-6xl px-4 py-8">
        <header class="flex flex-wrap items-center gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            @if ($shop->logoUrl('large'))
                <img src="{{ $shop->logoUrl('large') }}" alt="" width="80" height="80" class="size-20 shrink-0 rounded-2xl object-cover ring-1 ring-stone-200">
            @else
                <span class="flex size-20 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-700">{{ icon('store', 'size-10') }}</span>
            @endif
            <div class="min-w-0 flex-1">
                <h1 class="flex flex-wrap items-center gap-2 text-2xl font-bold">
                    <span class="break-words">{{ $shop->name }}</span>
                    @if ($shop->user->isVerified())
                        @include('partials.verified-badge')
                    @endif
                </h1>
                <p class="mt-1 text-sm text-stone-600">
                    @if ($shop->city){{ $shop->city->label }} · @endif{{ __('account.shop.member_since', ['date' => $shop->user->created_at->translatedFormat('M Y')]) }}
                </p>
                @include('partials.seller-rating', ['seller' => $shop->user])
                @if ($shop->hours)
                    <p class="mt-1 text-sm text-stone-600">{{ icon('clock', 'inline size-4') }} {{ $shop->hours }}</p>
                @endif
            </div>
        </header>

        @if ($shop->description)
            <p class="mt-4 whitespace-pre-line rounded-2xl bg-white p-5 text-stone-700 ring-1 ring-stone-200">{{ $shop->description }}</p>
        @endif

        <h2 class="mt-8 text-lg font-bold">{{ __('account.shop.ads') }}</h2>
        @if ($ads->isEmpty())
            <p class="mt-3 rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center text-stone-600">{{ __('account.shop.no_ads') }}</p>
        @else
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                @foreach ($ads as $ad)
                    @include('ads._card', ['ad' => $ad])
                @endforeach
            </div>
            <div class="mt-6">{{ $ads->links() }}</div>
        @endif
    </section>
@endsection
