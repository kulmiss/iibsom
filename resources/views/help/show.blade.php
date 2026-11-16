@extends('layouts.app')

@section('title', $article['title'])

@section('content')
    <article class="mx-auto max-w-2xl px-4 py-8">
        <a href="{{ lroute('help') }}" class="text-sm text-brand-700 hover:underline">← {{ __('site.help.all') }}</a>
        <h1 class="mt-2 text-2xl font-bold sm:text-3xl">{{ $article['title'] }}</h1>
        <div class="prose-iibsom mt-4">{{ $article['html'] }}</div>

        @if ($others !== [])
            <h2 class="mt-10 font-bold">{{ __('site.help.more') }}</h2>
            <ul class="mt-3 space-y-2">
                @foreach ($others as $item)
                    <li><a href="{{ lroute('help.show', ['article' => $item['slug']]) }}" class="text-brand-700 hover:underline">{{ $item['title'] }}</a></li>
                @endforeach
            </ul>
        @endif

        @include('help._contact')
    </article>
@endsection
