@extends('layouts.app')

@section('title', __('site.home.title'))

@section('content')
    <section class="bg-brand-700 text-white">
        <div class="mx-auto max-w-6xl px-4 py-10 sm:py-14">
            <h1 class="text-2xl font-bold sm:text-4xl">{{ __('site.tagline') }}</h1>
            <p class="mt-2 max-w-2xl text-brand-50 sm:text-lg">{{ __('site.home.intro') }}</p>

            {{-- Search starts working in the search PR. --}}
            <form class="mt-6 flex max-w-2xl gap-2" role="search" action="#" method="get">
                <label for="q" class="sr-only">{{ __('site.home.search_placeholder') }}</label>
                <input id="q" name="q" type="search" placeholder="{{ __('site.home.search_placeholder') }}"
                       class="min-w-0 flex-1 rounded-lg border-0 bg-white px-4 py-3 text-stone-900 placeholder:text-stone-500 focus:ring-2 focus:ring-brand-200">
                <button type="submit" class="rounded-lg bg-accent-500 px-5 py-3 font-semibold text-stone-900 hover:bg-accent-400">
                    {{ __('site.home.search_button') }}
                </button>
            </form>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-8">
        <p class="rounded-lg border border-dashed border-stone-300 bg-white p-6 text-center text-stone-600">
            {{ __('site.home.coming_soon') }}
        </p>
    </section>
@endsection
