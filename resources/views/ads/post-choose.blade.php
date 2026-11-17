@extends('layouts.app')

@section('title', __('ads.post.title'))

@section('content')
    <section class="mx-auto max-w-3xl px-4 py-6">
        <p class="text-sm font-medium text-brand-700">{{ __('ads.post.step', ['step' => 1]) }}</p>
        <h1 class="text-2xl font-bold">{{ __('ads.post.choose') }}</h1>
        <p class="mt-1 text-stone-600">{{ __('ads.post.choose_help') }}</p>

        <div class="mt-5 space-y-2">
            @foreach ($categories as $main)
                <details class="group overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-stone-200" @if ($loop->first) open @endif>
                    <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700">{{ icon($main['icon']) }}</span>
                        <span class="flex-1 font-semibold">{{ translated($main['name']) }}</span>
                        {{ icon('chevron-down', 'size-5 text-stone-400 transition group-open:rotate-180') }}
                    </summary>
                    <ul class="grid gap-1 border-t border-stone-100 p-2 sm:grid-cols-2">
                        @foreach ($main['children'] as $sub)
                            <li>
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
