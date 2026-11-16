@extends('layouts.app')

@section('title', __('ads.searches.title'))

@section('content')
    <section class="mx-auto max-w-2xl px-4 py-8">
        <h1 class="text-2xl font-bold">{{ __('ads.searches.title') }}</h1>
        <p class="mt-1 text-stone-600">{{ __('ads.searches.intro') }}</p>

        @if ($searches->isEmpty())
            <div class="mt-6 rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center text-stone-600">
                {{ icon('bell', 'mx-auto size-8 text-stone-400') }}
                <p class="mt-2">{{ __('ads.searches.empty') }}</p>
            </div>
        @else
            <ul class="mt-6 divide-y divide-stone-100 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-stone-200">
                @foreach ($searches as $search)
                    <li class="flex flex-wrap items-center gap-3 px-5 py-4">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium break-words">{{ $search->label }}</p>
                            @if ($search->last_sent_at)
                                <p class="text-xs text-stone-500">{{ __('ads.searches.last_sent', ['date' => $search->last_sent_at->translatedFormat('j M Y')]) }}</p>
                            @endif
                        </div>
                        <a href="{{ $search->url() }}" class="chip ring-stone-200">{{ __('ads.searches.open') }}</a>
                        <form method="post" action="{{ lroute('searches.destroy', ['search' => $search->id]) }}" data-confirm="{{ __('ads.searches.delete_confirm') }}">
                            @csrf
                            @method('delete')
                            <button type="submit" class="chip text-red-700 ring-red-200">{{ __('ads.searches.delete') }}</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
