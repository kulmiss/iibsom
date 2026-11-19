@extends('layouts.app')

@section('title', __('ads.finder.title'))
@section('description', __('ads.finder.description'))

@section('content')
    {{-- All categories (PR 84, switch "catalog.finder"). The search box needs JavaScript; without it the full list
         below is enough. Each group opens to its sub categories; counts are live ads. --}}
    <section class="mx-auto max-w-3xl px-4 py-6">
        <nav class="text-sm text-stone-500" aria-label="Breadcrumb">
            <ol class="flex flex-wrap items-center gap-x-1">
                <li><a href="{{ lroute('home') }}" class="hover:underline">{{ __('site.nav.home') }}</a></li>
                <li class="flex items-center gap-x-1"><span aria-hidden="true">›</span><span aria-current="page">{{ __('ads.finder.title') }}</span></li>
            </ol>
        </nav>
        <h1 class="mt-2 text-2xl font-bold">{{ __('ads.finder.title') }}</h1>

        <div class="relative mt-4 hidden [.js_&]:block">
            <label for="finder-q" class="sr-only">{{ __('ads.finder.search') }}</label>
            <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center text-stone-400">{{ icon('search', 'size-5') }}</span>
            <input id="finder-q" type="search" autocomplete="off" maxlength="60" class="form-input ps-10" placeholder="{{ __('ads.finder.search') }}" data-finder-search>
        </div>
        <p class="mt-3 hidden rounded-xl bg-white p-4 text-sm text-stone-600 ring-1 ring-stone-200" role="status" data-finder-none>{{ __('ads.finder.none') }}</p>

        <ul class="mt-4 space-y-2" data-finder>
            @foreach ($mains as $main)
                <li data-finder-group data-names="{{ mb_strtolower(implode(' ', (array) $main['name'])) }}">
                    <details class="group overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-stone-200">
                        <summary class="flex cursor-pointer list-none items-center gap-3 p-3 [&::-webkit-details-marker]:hidden">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700">{{ icon($main['icon'] ?? 'tag', 'size-6') }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold">{{ translated($main['name']) }}</span>
                                <span class="block text-xs text-stone-500">{{ trans_choice('site.ads_count', $main['ads_count'], ['count' => number_format($main['ads_count'])]) }}</span>
                            </span>
                            <span class="text-stone-400 transition group-open:rotate-90 rtl:rotate-180 rtl:group-open:rotate-90">{{ icon('chevron-right', 'size-5') }}</span>
                        </summary>
                        <ul class="border-t border-stone-100 p-1.5">
                            <li>
                                <a href="{{ lroute('browse.category', ['category' => $main['slug']]) }}" class="flex items-center gap-3 rounded-lg px-2 py-2 text-sm font-medium text-brand-800 hover:bg-brand-50">
                                    {{ __('ads.browse.all_in', ['category' => translated($main['name'])]) }}
                                </a>
                            </li>
                            @foreach ($main['visible'] as $sub)
                                <li data-finder-item data-names="{{ mb_strtolower(implode(' ', (array) $sub['name'])) }}">
                                    <a href="{{ lroute('browse.category', ['category' => $sub['slug']]) }}" class="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-brand-50">
                                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-stone-50 text-brand-700">{{ icon($sub['icon'] ?? 'tag', 'size-5') }}</span>
                                        <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ translated($sub['name']) }}</span>
                                        <span class="shrink-0 text-xs text-stone-500">{{ trans_choice('site.ads_count', $sub['ads_count'], ['count' => number_format($sub['ads_count'])]) }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                </li>
            @endforeach
        </ul>
    </section>
@endsection
