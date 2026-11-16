@extends('layouts.app')

@section('title', __('site.errors.not_found_title'))

@section('content')
    <section class="mx-auto max-w-xl px-4 py-16 text-center">
        <h1 class="text-2xl font-bold">{{ __('site.errors.not_found_title') }}</h1>
        <p class="mt-2 text-stone-600">{{ __('site.errors.not_found_text') }}</p>
        <a href="{{ lroute('home') }}" class="btn-primary mt-6">{{ __('site.errors.back_home') }}</a>
    </section>
@endsection
