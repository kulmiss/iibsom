@extends('layouts.app')

@section('title', $other->name.' · '.__('chat.title'))

@push('head')
    <meta name="robots" content="noindex">
@endpush

@push('scripts')
    {{-- The chat keeps polling for new messages: the language switch loads this page fully (PR 84). --}}
    <meta name="page-script" content="chat">
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

        @if (session('status'))
            <p class="mt-3 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-900 ring-1 ring-green-200" role="status">{{ session('status') }}</p>
        @endif

        @if ($canRate)
            <details class="mt-3 rounded-2xl bg-white p-3 ring-1 ring-stone-200" @if ($errors->has('stars') || $errors->has('comment')) open @endif>
                <summary class="flex cursor-pointer list-none items-center gap-2 font-semibold text-brand-800">
                    {{ icon('star', 'size-5 fill-amber-400 text-amber-500') }}
                    @if ($myReview)
                        <span>{{ __('chat.review.yours') }}</span> @include('partials.stars', ['stars' => $myReview->stars])
                        <span class="ms-auto text-sm font-medium text-brand-700 underline">{{ __('chat.review.change') }}</span>
                    @else
                        <span>{{ __('chat.review.title') }}</span>
                    @endif
                </summary>
                @if ($myReview?->hidden_at)
                    <p class="mt-2 rounded-lg bg-stone-100 px-3 py-2 text-xs text-stone-700">{{ __('chat.review.hidden') }}</p>
                @endif
                <form method="post" action="{{ lroute('chat.review', ['conversation' => $conversation->id]) }}" class="mt-3 space-y-3">
                    @csrf
                    <fieldset>
                        <legend class="form-label">{{ __('chat.review.stars') }}</legend>
                        <div class="flex gap-1">
                            @for ($i = 1; $i <= 5; $i++)
                                <label class="cursor-pointer">
                                    <input type="radio" name="stars" value="{{ $i }}" class="peer sr-only" required @checked((int) old('stars', $myReview?->stars) === $i)>
                                    <span class="flex size-11 items-center justify-center rounded-lg ring-1 ring-stone-200 peer-checked:bg-amber-50 peer-checked:ring-2 peer-checked:ring-amber-500 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-600" title="{{ __('chat.review.star_label', ['count' => $i]) }}">
                                        <span class="text-base font-bold">{{ $i }}</span>{{ icon('star', 'size-4 fill-amber-400 text-amber-500') }}
                                        <span class="sr-only">{{ __('chat.review.star_label', ['count' => $i]) }}</span>
                                    </span>
                                </label>
                            @endfor
                        </div>
                        @error('stars') <p class="form-error">{{ $message }}</p> @enderror
                    </fieldset>
                    <div>
                        <label for="comment" class="form-label">{{ __('chat.review.comment') }}</label>
                        <textarea id="comment" name="comment" rows="2" maxlength="{{ \App\Models\SellerReview::COMMENT_MAX }}" class="form-input" placeholder="{{ __('chat.review.comment_placeholder') }}">{{ old('comment', $myReview?->comment) }}</textarea>
                        @error('comment') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <p class="text-xs text-stone-500">{{ __('chat.review.help') }}</p>
                    <button type="submit" class="btn-primary w-full py-2.5">{{ __('chat.review.send') }}</button>
                </form>
            </details>
        @elseif (feature('seller_ratings') && $conversation->buyer_id === $user->id && $messages->isNotEmpty())
            <p class="mt-3 text-xs text-stone-500">{{ __('chat.review.wait') }}</p>
        @endif

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
            <form method="post" action="{{ lroute('chat.send', ['conversation' => $conversation->id]) }}" class="sticky bottom-16 mt-4 flex flex-wrap items-end gap-2 rounded-2xl bg-white p-2 shadow-sm ring-1 ring-stone-200 lg:bottom-2" data-chat-form>
                @csrf
                {{-- Inside the sticky box, above the text, so the note is seen on phones (the bottom bar covers anything below). --}}
                <p class="form-error order-first m-0 w-full px-1" role="alert" data-chat-error @error('body') @else hidden @enderror>@error('body'){{ $message }}@enderror</p>
                <label for="body" class="sr-only">{{ __('chat.message') }}</label>
                <textarea id="body" name="body" rows="{{ old('body', $draft) !== '' ? 3 : 1 }}" maxlength="{{ \App\Models\Message::MAX_LENGTH }}" required placeholder="{{ __('chat.placeholder') }}" class="max-h-40 min-h-11 min-w-0 flex-1 resize-y rounded-xl border-0 bg-stone-50 px-3 py-2.5 focus:ring-2 focus:ring-brand-600" @if ($draft !== '') autofocus @endif>{{ old('body', $draft) }}</textarea>
                <button type="submit" class="btn-sell h-11 px-4" aria-label="{{ __('chat.send') }}">{{ icon('send', 'size-5') }}</button>
            </form>
        @endif
    </section>
@endsection
