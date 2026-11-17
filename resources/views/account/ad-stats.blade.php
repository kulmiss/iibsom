@extends('layouts.app')

@section('title', __('ads.stats.title'))

@section('content')
    @php($max = max(1, ...array_column($days, 'views')))
    <section class="mx-auto max-w-2xl px-4 py-8">
        <a href="{{ lroute('account.ads') }}" class="text-sm text-brand-700 hover:underline">← {{ __('account.account.my_ads') }}</a>
        <h1 class="mt-2 text-2xl font-bold">{{ __('ads.stats.title') }}</h1>
        <p class="mt-1 truncate text-stone-600"><a href="{{ $ad->url() }}" class="hover:underline">{{ $ad->title }}</a></p>

        <h2 class="mt-6 text-sm font-semibold text-stone-600">{{ __('ads.stats.last_30') }}</h2>
        <dl class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach (['views' => __('ads.stats.views'), 'phone' => __('ads.stats.phone'), 'whatsapp' => __('ads.stats.whatsapp'), 'call' => __('ads.stats.call')] as $key => $label)
                <div class="flex flex-col justify-between gap-1 rounded-xl bg-white p-4 ring-1 ring-stone-200">
                    <dt class="text-xs text-stone-600">{{ $label }}</dt>
                    <dd class="mt-1 text-2xl font-bold">{{ number_format($totals[$key]) }}</dd>
                </div>
            @endforeach
        </dl>

        <h2 class="mt-8 text-sm font-semibold text-stone-600">{{ __('ads.stats.daily') }}</h2>
        <div class="mt-3 flex h-40 items-end gap-1 rounded-xl bg-white p-3 ring-1 ring-stone-200" aria-hidden="true">
            @foreach ($days as $d)
                <div class="flex h-full flex-1 flex-col items-center gap-1">
                    <div class="flex w-full flex-1 items-end">
                        <div class="w-full rounded-t bg-brand-600" style="height: {{ max(2, (int) round($d['views'] / $max * 100)) }}%" title="{{ $d['day']->translatedFormat('j M') }}: {{ $d['views'] }}"></div>
                    </div>
                    <span class="text-[10px] text-stone-500">{{ $d['day']->format('j') }}</span>
                </div>
            @endforeach
        </div>
        <table class="sr-only">
            <caption>{{ __('ads.stats.daily') }}</caption>
            <thead><tr><th scope="col">{{ __('ads.stats.day') }}</th><th scope="col">{{ __('ads.stats.views') }}</th></tr></thead>
            <tbody>
                @foreach ($days as $d)
                    <tr><td>{{ $d['day']->translatedFormat('j M Y') }}</td><td>{{ $d['views'] }}</td></tr>
                @endforeach
            </tbody>
        </table>

        <p class="mt-6 text-sm text-stone-600">{{ __('ads.stats.tip') }}</p>
        <p class="mt-2 text-xs text-stone-500">{{ __('ads.stats.privacy') }}</p>
    </section>
@endsection
