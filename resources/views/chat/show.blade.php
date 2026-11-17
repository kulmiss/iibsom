@extends('layouts.app')

@section('title', $other->name.' · '.__('chat.title'))

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <section class="mx-auto flex max-w-2xl flex-col px-4 py-6">
        <a href="{{ lroute('chat') }}" class="text-sm text-brand-700 hover:underline">← {{ __('chat.all') }}</a>

        <header class="mt-2 flex items-center gap-3 rounded-2xl bg-white p-3 ring-1 ring-stone-200">
            <span class="size-12 shrink-0 overflow-hidden rounded-lg bg-stone-100">
                @if ($conversation->ad?->cover)
                    <img src="{{ $conversation->ad->cover->url('thumb') }}" alt="" width="48" height="48" class="size-full object-cover">
                @endif
            </span>
            <div class="min-w-0 flex-1">
                <h1 class="truncate font-bold">{{ $other->name }}</h1>
                @if ($conversation->ad && ! $conversation->ad->trashed())
                    <a href="{{ $conversation->ad->url() }}" class="block truncate text-sm text-brand-700 hover:underline">{{ __('chat.about', ['title' => $conversation->ad->title]) }}</a>
                @else
                    <p class="text-sm text-stone-500">{{ __('chat.ad_gone') }}</p>
                @endif
            </div>
            @if ($conversation->blocked_by === null || $conversation->blocked_by === $user->id)
                <form method="post" action="{{ lroute('chat.block', ['conversation' => $conversation->id]) }}" @if ($conversation->blocked_by === null) data-confirm="{{ __('chat.block_confirm') }}" @endif>
                    @csrf
                    <button type="submit" class="chip text-red-700 ring-red-200">{{ $conversation->blocked_by === null ? __('chat.block') : __('chat.unblock') }}</button>
                </form>
            @endif
        </header>

        <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900 ring-1 ring-amber-200">{{ __('chat.safety') }}</p>

        <ol class="mt-3 flex flex-col gap-2" data-chat-list data-since="{{ lroute('chat.since', ['conversation' => $conversation->id]) }}" data-last="{{ $messages->last()?->id ?? 0 }}" aria-live="polite">
            @forelse ($messages as $m)
                <li id="m{{ $m->id }}" data-id="{{ $m->id }}" class="max-w-[85%] rounded-2xl px-3 py-2 text-sm {{ $m->sender_id === $user->id ? 'self-end bg-brand-700 text-white' : 'self-start bg-white ring-1 ring-stone-200' }}">
                    <p class="whitespace-pre-line break-words">{{ $m->body }}</p>
                    <p class="mt-0.5 text-end text-[11px] opacity-75">{{ $m->clock() }}</p>
                </li>
            @empty
                <li class="text-center text-sm text-stone-500" data-chat-empty>{{ __('chat.first') }}</li>
            @endforelse
        </ol>

        @if ($conversation->blocked_by !== null)
            <p class="mt-4 rounded-xl bg-stone-100 p-3 text-center text-sm text-stone-700">{{ $conversation->blocked_by === $user->id ? __('chat.blocked_by_you') : __('chat.blocked_by_them') }}</p>
        @else
            <form method="post" action="{{ lroute('chat.send', ['conversation' => $conversation->id]) }}" class="sticky bottom-16 mt-4 flex items-end gap-2 rounded-2xl bg-white p-2 shadow-sm ring-1 ring-stone-200 lg:bottom-2" data-chat-form>
                @csrf
                <label for="body" class="sr-only">{{ __('chat.message') }}</label>
                <textarea id="body" name="body" rows="1" maxlength="{{ \App\Models\Message::MAX_LENGTH }}" required placeholder="{{ __('chat.placeholder') }}" class="max-h-40 min-h-11 flex-1 resize-y rounded-xl border-0 bg-stone-50 px-3 py-2.5 focus:ring-2 focus:ring-brand-600">{{ old('body') }}</textarea>
                <button type="submit" class="btn-sell h-11 px-4" aria-label="{{ __('chat.send') }}">{{ icon('send', 'size-5') }}</button>
            </form>
            <p class="form-error mt-1" data-chat-error @error('body') @else hidden @enderror>@error('body'){{ $message }}@enderror</p>
        @endif
    </section>
@endsection
