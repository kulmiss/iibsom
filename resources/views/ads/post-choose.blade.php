@extends('layouts.app')

@section('title', __('ads.post.title'))

@if (feature('post.category_search'))
    @push('scripts')
        @vite('resources/js/post-ad.js')
    @endpush
@endif

@section('content')
    <section class="mx-auto max-w-3xl px-4 py-6">
        @include('ads._post-steps', ['current' => 1])
        @unless (feature('post.steps'))
            <p class="text-sm font-medium text-brand-700">{{ __('ads.post.step', ['step' => 1]) }}</p>
        @endunless
        <h1 class="text-2xl font-bold">{{ __('ads.post.choose') }}</h1>
        <p class="mt-1 text-stone-600">{{ __('ads.post.choose_help') }}</p>
        @if (feature('help.posting_rules') && feature('help.centre'))
            <p class="mt-2 text-sm"><a href="{{ lroute('help.show', ['article' => 'posting-rules']) }}" class="inline-flex items-center gap-1 font-medium text-brand-700 underline">{{ icon('shield-check', 'size-4') }} {{ __('ads.mine.posting_rules') }}</a></p>
        @endif
        @if (feature('help.how_to_sell') && feature('help.centre'))
            <p class="mt-2 text-sm"><a href="{{ lroute('help.show', ['article' => 'how-to-sell']) }}" class="inline-flex items-center gap-1 font-medium text-brand-700 underline">{{ icon('lightbulb', 'size-4') }} {{ __('ads.post.how_to_sell') }}</a></p>
        @endif

        @feature('post.category_search')
            {{-- Shown by post-ad.js; without JavaScript the full list below is enough. --}}
            <div class="relative mt-5" data-cat-search-box hidden>
                <label for="cat-search" class="sr-only">{{ __('ads.post.search_category') }}</label>
                <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center text-stone-400">{{ icon('search', 'size-5') }}</span>
                <input id="cat-search" type="search" autocomplete="off" class="form-input ps-10" placeholder="{{ __('ads.post.search_category') }}" data-cat-search>
                <p class="mt-3 hidden rounded-xl bg-white p-4 text-sm text-stone-600 ring-1 ring-stone-200" role="status" data-cat-none>{{ __('ads.post.no_category') }}</p>
            </div>
        @endfeature

        <div class="mt-5 space-y-2" data-cat-groups>
            @foreach ($categories as $main)
                <details class="group overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-stone-200" @if ($loop->first) open @endif
                         data-cat-group data-names="{{ mb_strtolower(implode(' ', (array) $main['name'])) }}">
                    <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700">{{ icon($main['icon']) }}</span>
                        <span class="flex-1 font-semibold">{{ translated($main['name']) }}</span>
                        {{ icon('chevron-down', 'size-5 text-stone-400 transition group-open:rotate-180') }}
                    </summary>
                    <ul class="grid gap-1 border-t border-stone-100 p-2 sm:grid-cols-2">
                        @foreach ($main['children'] as $sub)
                            <li data-cat-item data-names="{{ mb_strtolower(implode(' ', (array) $sub['name'])) }}">
                                <a href="{{ lroute('post.form', ['category' => $sub['slug']]) }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 hover:bg-brand-50">
                                    {{ icon($sub['icon'], 'size-5 text-brand-700') }}
                                    <span>{{ translated($sub['name']) }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endforeach
        </div>
    </section>
@endsection
