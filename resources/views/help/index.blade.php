@extends('layouts.app')

@section('title', __('site.help.title'))

@section('content')
    <section class="mx-auto max-w-2xl px-4 py-8">
        <h1 class="text-2xl font-bold sm:text-3xl">{{ __('site.help.heading') }}</h1>
        <p class="mt-2 text-stone-600">{{ __('site.help.intro') }}</p>

        <ul class="mt-6 divide-y divide-stone-100 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-stone-200">
            @foreach ($articles as $item)
                <li>
                    <a href="{{ lroute('help.show', ['article' => $item['slug']]) }}" class="flex items-center gap-3 px-5 py-4 hover:bg-stone-50">
                        {{ icon('circle-help', 'size-5 shrink-0 text-brand-700') }}<span class="flex-1">{{ $item['title'] }}</span>{{ icon('chevron-right', 'size-4 text-stone-400') }}
                    </a>
                </li>
            @endforeach
        </ul>

        @include('help._contact')
    </section>
@endsection
