@extends('layouts.app')

@section('title', $page->titleText())

@section('content')
    <article class="mx-auto max-w-2xl px-4 py-8">
        <h1 class="text-2xl font-bold sm:text-3xl">{{ $page->titleText() }}</h1>
        <div class="prose-iibsom mt-4">{{ $page->bodyHtml() }}</div>
        <p class="mt-8 text-xs text-stone-500">{{ __('site.page_updated', ['date' => mdate($page->updated_at)]) }}</p>
    </article>
@endsection
