@extends('layouts.app')

@section('title', __('site.errors.not_found_title'))

@section('content')
    <section class="mx-auto max-w-xl px-4 py-12">
        <x-empty-state icon="map-pin" plain :title="__('site.errors.not_found_title')" :text="__('site.errors.not_found_text')">
            <form method="get" action="{{ lroute('search') }}" role="search" class="flex w-full max-w-sm gap-2">
                <label for="nf-q" class="sr-only">{{ __('site.home.search_placeholder') }}</label>
                <input id="nf-q" name="q" type="search" class="form-input" placeholder="{{ __('site.home.search_placeholder') }}" maxlength="100">
                <button type="submit" class="btn-primary">{{ icon('search', 'size-4') }}<span class="sr-only">{{ __('site.home.search_placeholder') }}</span></button>
            </form>
            <a href="{{ lroute('home') }}" class="btn-chip">{{ __('site.errors.back_home') }}</a>
        </x-empty-state>
    </section>
@endsection
