@extends('layouts.app')

@section('title', __('chat.title'))

@section('content')
    <section class="mx-auto max-w-2xl px-4 py-8">
        <h1 class="text-2xl font-bold">{{ __('chat.title') }}</h1>

        @if ($conversations->isEmpty())
            <div class="mt-6 rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center text-stone-600">
                {{ icon('message-circle', 'mx-auto size-8 text-stone-400') }}
                <p class="mt-2">{{ __('chat.empty_inbox') }}</p>
            </div>
        @else
            <ul class="mt-6 divide-y divide-stone-100 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-stone-200">
                @foreach ($conversations as $c)
                    @php($unread = $c->isUnreadFor($user))
                    <li>
                        <a href="{{ lroute('chat.show', ['conversation' => $c->id]) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-stone-50">
                            <span class="size-12 shrink-0 overflow-hidden rounded-lg bg-stone-100">
                                @if ($c->ad?->cover)
                                    <img src="{{ $c->ad->cover->url('thumb') }}" alt="" width="48" height="48" loading="lazy" class="size-full object-cover">
                                @endif
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-2">
                                    <span class="truncate {{ $unread ? 'font-bold' : 'font-semibold' }}">{{ $c->other($user)->name }}</span>
                                    @if ($unread)<span class="rounded-full bg-brand-700 px-1.5 text-[11px] font-semibold text-white">{{ __('chat.new') }}</span>@endif
                                    <span class="ms-auto shrink-0 text-xs text-stone-500">{{ $c->last_message_at?->diffForHumans(short: true) }}</span>
                                </span>
                                <span class="block truncate text-xs text-stone-500">{{ $c->ad?->title }}</span>
                                <span class="block truncate text-sm {{ $unread ? 'text-stone-900' : 'text-stone-600' }}">{{ $c->last_body }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mt-6">{{ $conversations->links() }}</div>
        @endif
    </section>
@endsection
