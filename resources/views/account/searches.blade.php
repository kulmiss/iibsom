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
                            @if ($search->muted)
                                <p class="flex items-center gap-1 text-xs font-semibold text-amber-800">{{ icon('bell-off', 'size-3.5') }} {{ __('ads.searches.muted') }}</p>
                            @endif
                            @if ($search->last_sent_at)
                                <p class="text-xs text-stone-500">{{ __('ads.searches.last_sent', ['date' => mdate($search->last_sent_at)]) }}</p>
                            @endif
                        </div>
                        <a href="{{ $search->url() }}" class="chip ring-stone-200">{{ __('ads.searches.open') }}</a>
                        @feature('saved_searches.manage')
                            <form method="post" action="{{ lroute('searches.update', ['search' => $search->id]) }}">
                                @csrf
                                @method('patch')
                                <input type="hidden" name="muted" value="{{ $search->muted ? 0 : 1 }}">
                                <button type="submit" class="chip gap-1 ring-stone-200">{{ icon($search->muted ? 'bell' : 'bell-off', 'size-4') }} {{ $search->muted ? __('ads.searches.unmute') : __('ads.searches.mute') }}</button>
                            </form>
                            <details class="w-full" @if ($errors->has('label') && (int) old('search_id') === $search->id) open @endif>
                                <summary class="cursor-pointer text-sm font-medium text-brand-700">{{ __('ads.searches.rename') }}</summary>
                                <form method="post" action="{{ lroute('searches.update', ['search' => $search->id]) }}" class="mt-2 flex gap-2">
                                    @csrf
                                    @method('patch')
                                    <input type="hidden" name="search_id" value="{{ $search->id }}">
                                    <label for="label-{{ $search->id }}" class="sr-only">{{ __('ads.searches.name') }}</label>
                                    <input id="label-{{ $search->id }}" name="label" maxlength="80" required value="{{ $search->label }}" class="form-input min-w-0 flex-1">
                                    <button type="submit" class="btn-primary shrink-0">{{ __('ads.searches.save_name') }}</button>
                                </form>
                                @if ((int) old('search_id') === $search->id) @error('label')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror @endif
                            </details>
                        @endfeature
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
